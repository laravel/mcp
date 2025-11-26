<?php

declare(strict_types=1);

namespace Laravel\Mcp\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;

class UriTemplate implements Stringable
{
    private const MAX_TEMPLATE_LENGTH = 1_000_000;

    private const MAX_VARIABLE_LENGTH = 1_000_000;

    private const MAX_TEMPLATE_EXPRESSIONS = 10_000;

    private const MAX_REGEX_LENGTH = 1_000_000;

    private const URI_TEMPLATE_PATTERN = '/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\/.*{[^{}]+}.*/';

    /** @var list<string> */
    private array $variableNames = [];

    private ?string $compiledRegex = null;

    public function __construct(private readonly string $template)
    {
        $this->validateLength($template, self::MAX_TEMPLATE_LENGTH, 'Template');

        if (! preg_match(self::URI_TEMPLATE_PATTERN, $template)) {
            throw new InvalidArgumentException('Invalid URI template: must be a valid URI template with at least one placeholder.');
        }

        $this->variableNames = $this->extractVariableNames($template);
    }

    /**
     * @return array<string, string>|null
     */
    public function match(string $uri): ?array
    {
        $this->validateLength($uri, self::MAX_TEMPLATE_LENGTH, 'URI');

        $this->compiledRegex ??= $this->compileRegex();

        if (! preg_match($this->compiledRegex, $uri, $matches)) {
            return null;
        }

        $result = [];

        foreach ($this->variableNames as $i => $name) {
            $result[$name] = $matches[$i + 1] ?? '';
        }

        return $result;
    }

    public function __toString(): string
    {
        return $this->template;
    }

    private function validateLength(string $str, int $max, string $context): void
    {
        throw_if(
            Str::length($str) > $max,
            InvalidArgumentException::class,
            sprintf('%s exceeds the maximum length of %d characters (received %d)', $context, $max, Str::length($str))
        );
    }

    /**
     * @return list<string>
     */
    private function extractVariableNames(string $template): array
    {
        $expressionCount = 0;
        $names = [];

        if (! preg_match_all('/\{(\w+)}/', $template, $matches)) {
            return [];
        }

        foreach ($matches[1] as $name) {
            $expressionCount++;

            throw_if(
                $expressionCount > self::MAX_TEMPLATE_EXPRESSIONS,
                InvalidArgumentException::class,
                sprintf('Template contains too many expressions (max %d)', self::MAX_TEMPLATE_EXPRESSIONS)
            );

            $this->validateLength($name, self::MAX_VARIABLE_LENGTH, 'Variable name');
            $names[] = $name;
        }

        return $names;
    }

    private function compileRegex(): string
    {
        $regexParts = [];

        $segments = preg_split('/(\{\w+})/', $this->template, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        throw_unless(
            $segments,
            InvalidArgumentException::class,
            'Failed to compile URI template regex: preg_split error'
        );

        foreach ($segments as $segment) {
            $isVariable = preg_match('/^\{(\w+)}$/', $segment);

            throw_if(
                $isVariable === false,
                InvalidArgumentException::class,
                'Failed to validate template segment: preg_match error'
            );

            $regexParts[] = $isVariable === 1 ? '([^/]+)' : preg_quote($segment, '#');
        }

        $pattern = '#^'.implode('', $regexParts).'$#';

        $this->validateLength($pattern, self::MAX_REGEX_LENGTH, 'Generated regex pattern');

        return $pattern;
    }
}
