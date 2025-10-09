<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Resources;

use const PHP_INT_MAX;
use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;

use DomainException;
use LogicException;
use RuntimeException;

class Uri
{
    /**
     * This string defines the characters that are automatically considered separators in front of
     * optional placeholders (with default and no static text following). Such a single separator
     * can be left out together with the optional placeholder from matching and generating URLs.
     */
    public const SEPARATORS = '/,;.:-_~+*=@|';

    /**
     * The maximum supported length of a PCRE subpattern name
     * http://pcre.org/current/doc/html/pcre2pattern.html#SEC16.
     *
     * @internal
     */
    public const VARIABLE_MAXIMUM_LENGTH = 32;

    public static function path(string $uri): string
    {
        $components = parse_url($uri);

        $path = rtrim(($components['host'] ?? '').($components['path'] ?? ''), '/');

        return $path === '' ? '/' : $path;
    }

    /**
     * @return array<string, mixed>
     */
    public static function pathRegex(string $uri): array
    {
        $path = Uri::path($uri);

        $tokens = [];
        $variables = [];
        $matches = [];
        $pos = 0;
        $defaultSeparator = '/';
        $useUtf8 = preg_match('//u', $path) !== false;

        // Match all variables enclosed in "{}" and iterate over them. But we only want to match the innermost variable
        // in case of nested "{}", e.g. {foo{bar}}. This in ensured because \w does not match "{" or "}" itself.
        preg_match_all('#\{(!)?([\w\x80-\xFF]+)\}#', $path, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as $match) {
            $important = $match[1][1] >= 0;
            $varName = $match[2][0];
            // get all static text preceding the current variable
            $precedingText = substr($path, $pos, $match[0][1] - $pos);
            $pos = $match[0][1] + \strlen($match[0][0]);

            if ($precedingText === '') {
                $precedingChar = '';
            } elseif ($useUtf8) {
                preg_match('/.$/u', $precedingText, $precedingChar);
                /** @phpstan-ignore offsetAccess.notFound */
                $precedingChar = $precedingChar[0];
            } else {
                $precedingChar = substr($precedingText, -1);
            }

            $isSeparator = $precedingChar !== '' && str_contains((string) static::SEPARATORS, $precedingChar);

            // A PCRE subpattern name must start with a non-digit. Also, a PHP variable cannot start with a digit so the
            // variable would not be usable as a Controller action argument.
            if (preg_match('/^\d/', $varName)) {
                throw new DomainException(\sprintf('Variable name "%s" cannot start with a digit in URI pattern "%s". Please use a different name.', $varName, $path));
            }

            if (\in_array($varName, $variables, true)) {
                throw new LogicException(\sprintf('URI pattern "%s" cannot reference variable name "%s" more than once.', $path, $varName));
            }

            if (\strlen($varName) > self::VARIABLE_MAXIMUM_LENGTH) {
                throw new DomainException(\sprintf('Variable name "%s" cannot be longer than %d characters in URI pattern "%s". Please use a shorter name.', $varName, self::VARIABLE_MAXIMUM_LENGTH, $path));
            }

            if ($isSeparator && $precedingText !== $precedingChar) {
                $tokens[] = ['text', substr($precedingText, 0, -\strlen($precedingChar))];
            } elseif (! $isSeparator && $precedingText !== '') {
                $tokens[] = ['text', $precedingText];
            }

            $followingPattern = substr($path, $pos);

            // Find the next static character after the variable that functions as a separator. By default, this separator and '/'
            // are disallowed for the variable. This default requirement makes sure that optional variables can be matched at all
            // and that the generating-matching-combination of URLs unambiguous, i.e. the params used for generating the URL are
            // the same that will be matched. Example: new Route('/{page}.{_format}', ['_format' => 'html'])
            // If {page} would also match the separating dot, {_format} would never match as {page} will eagerly consume everything.
            // Also, even if {_format} was not optional the requirement prevents that {page} matches something that was originally
            // part of {_format} when generating the URL, e.g. _format = 'mobile.html'.
            $nextSeparator = self::findNextSeparator($followingPattern, $useUtf8);

            $regexp = \sprintf(
                '[^%s%s]+',
                preg_quote($defaultSeparator),
                $defaultSeparator !== $nextSeparator && $nextSeparator !== '' ? preg_quote($nextSeparator) : ''
            );

            if (($nextSeparator !== '' && in_array(preg_match('#^\{[\w\x80-\xFF]+\}#', $followingPattern), [0, false], true)) || $followingPattern === '') {
                // When we have a separator, which is disallowed for the variable, we can optimize the regex with a possessive
                // quantifier. This prevents useless backtracking of PCRE and improves performance by 20% for matching those patterns.
                // Given the above example, there is no point in backtracking into {page} (that forbids the dot) when a dot must follow
                // after it. This optimization cannot be applied when the next char is no real separator or when the next variable is
                // directly adjacent, e.g. '/{x}{y}'.
                $regexp .= '+';
            }

            if ($important) {
                $token = ['variable', $isSeparator ? $precedingChar : '', $regexp, $varName, false, true];
            } else {
                $token = ['variable', $isSeparator ? $precedingChar : '', $regexp, $varName];
            }

            $tokens[] = $token;
            $variables[] = $varName;
        }

        if ($pos < \strlen($path)) {
            $tokens[] = ['text', substr($path, $pos)];
        }

        // find the first optional token
        $firstOptional = PHP_INT_MAX;
        for ($i = \count($tokens) - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            // variable is optional when it is not important and has a default value
            if ($token[0] === 'variable' && ! ($token[5] ?? false)) {
                $firstOptional = $i;
            } else {
                break;
            }
        }

        // compute the matching regexp
        $regexp = '';
        for ($i = 0, $nbToken = \count($tokens); $i < $nbToken; $i++) {
            $regexp .= self::computeRegexp($tokens, $i, $firstOptional);
        }

        $regexp = '{^'.$regexp.'$}sD';

        // enable Utf8 matching
        $regexp .= 'u';
        for ($i = 0, $nbToken = \count($tokens); $i < $nbToken; $i++) {
            if ($tokens[$i][0] === 'variable') {
                $tokens[$i][4] = true;
            }
        }

        return [
            'staticPrefix' => self::determineStaticPrefix($tokens),
            'regex' => $regexp,
            'tokens' => array_reverse($tokens),
            'variables' => $variables,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pathVariables(string $templateUri, string $uri): array
    {
        $path = static::path($uri);
        $regex = static::pathRegex($templateUri);

        if (count($regex['variables']) === 0) {
            return [];
        }

        preg_match($regex['regex'], $path, $matches);

        $values = array_slice($matches, 1);

        return array_intersect_key($values, array_flip($regex['variables']));
    }

    public static function scheme(string $uri): string
    {
        return parse_url($uri, PHP_URL_SCHEME) ?: throw new RuntimeException('Invalid URI provided');
    }

    /**
     * @param  list<array<int, bool|string>>  $tokens
     */
    private static function computeRegexp(array $tokens, int $index, int $firstOptional): string
    {
        $token = $tokens[$index];
        if ($token[0] === 'text') {
            // Text tokens
            return preg_quote((string) $token[1]);
        }

        // Variable tokens
        if ($index === 0 && $firstOptional === 0) {
            // When the only token is an optional variable token, the separator is required
            return \sprintf('%s(?P<%s>%s)?', preg_quote((string) $token[1]), $token[3], $token[2]);
        }

        $regexp = \sprintf('%s(?P<%s>%s)', preg_quote((string) $token[1]), $token[3], $token[2]);
        if ($index >= $firstOptional) {
            // Enclose each optional token in a subpattern to make it optional.
            // "?:" means it is non-capturing, i.e. the portion of the subject string that
            // matched the optional subpattern is not passed back.
            $regexp = "(?:{$regexp}";
            $nbTokens = \count($tokens);
            if ($nbTokens - 1 === $index) {
                // Close the optional subpatterns
                $regexp .= str_repeat(')?', $nbTokens - $firstOptional - ($firstOptional === 0 ? 1 : 0));
            }
        }

        return $regexp;
    }

    /**
     * @param  list<array<int, bool|string>>  $tokens
     */
    private static function determineStaticPrefix(array $tokens): string
    {
        if ($tokens[0][0] !== 'text') {
            return (string) ($tokens[0][1] === '/' ? '' : $tokens[0][1]);
        }

        $prefix = $tokens[0][1];

        if (isset($tokens[1][1]) && $tokens[1][1] !== '/') {
            $prefix .= $tokens[1][1];
        }

        return (string) $prefix;
    }

    private static function findNextSeparator(string $pattern, bool $useUtf8): string
    {
        if ($pattern === '') {
            // return empty string if pattern is empty or false (false which can be returned by substr)
            return '';
        }

        // first remove all placeholders from the pattern so we can find the next real static character
        if ('' === $pattern = preg_replace('#\{[\w\x80-\xFF]+\}#', '', $pattern)) {
            return '';
        }

        if ($useUtf8) {
            preg_match('/^./u', (string) $pattern, $pattern);
        }

        /** @phpstan-ignore offsetAccess.notFound, offsetAccess.notFound */
        return str_contains((string) static::SEPARATORS, $pattern[0]) ? $pattern[0] : '';
    }
}
