<?php

declare(strict_types=1);

namespace Laravel\Mcp\Support;

use InvalidArgumentException;
use Stringable;

class UriTemplate implements Stringable
{
    private const MAX_TEMPLATE_LENGTH = 1000000;

    private const MAX_VARIABLE_LENGTH = 1000000;

    private const MAX_TEMPLATE_EXPRESSIONS = 10000;

    private const MAX_REGEX_LENGTH = 1000000;

    private const OPERATORS = ['+', '#', '.', '/', '?', '&'];

    private string $template;

    /** @var list<string|array{name: string, operator: string, names: list<string>, exploded: bool}> */
    private array $parts;

    public function __construct(string $template)
    {
        $this->validateLength($template, self::MAX_TEMPLATE_LENGTH, 'Template');
        $this->template = $template;
        $this->parts = $this->parse($template);
    }

    public static function isTemplate(string $str): bool
    {
        return (bool) preg_match('/\{[^}\s]+\}/', $str);
    }

    /**
     * @return list<string>
     */
    public function getVariableNames(): array
    {
        $names = [];

        foreach ($this->parts as $part) {
            if (is_array($part)) {
                $names = array_merge($names, $part['names']);
            }
        }

        return $names;
    }

    /**
     * @param  array<string, string|list<string>>  $variables
     */
    public function expand(array $variables): string
    {
        $result = '';
        $hasQueryParam = false;

        foreach ($this->parts as $part) {
            if (is_string($part)) {
                $result .= $part;

                continue;
            }

            $expanded = $this->expandPart($part, $variables);

            if ($expanded === '') {
                continue;
            }

            if (($part['operator'] === '?' || $part['operator'] === '&') && $hasQueryParam) {
                $result .= str_replace('?', '&', $expanded);
            } else {
                $result .= $expanded;
            }

            if ($part['operator'] === '?' || $part['operator'] === '&') {
                $hasQueryParam = true;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string|list<string>>|null
     */
    public function match(string $uri): ?array
    {
        $this->validateLength($uri, self::MAX_TEMPLATE_LENGTH, 'URI');

        $pattern = '^';
        $names = [];

        foreach ($this->parts as $part) {
            if (is_string($part)) {
                $pattern .= $this->escapeRegExp($part);
            } else {
                $patterns = $this->partToRegExp($part);

                foreach ($patterns as $patternData) {
                    $pattern .= $patternData['pattern'];
                    $names[] = [
                        'name' => $patternData['name'],
                        'exploded' => $part['exploded'],
                        'optional' => $patternData['optional'] ?? false,
                    ];
                }
            }
        }

        $pattern .= '$';

        $this->validateLength($pattern, self::MAX_REGEX_LENGTH, 'Generated regex pattern');

        if (! preg_match('#'.$pattern.'#', $uri, $matches)) {
            return null;
        }

        $result = [];

        foreach ($names as $i => $nameData) {
            $name = $nameData['name'];
            $exploded = $nameData['exploded'];
            $value = $matches[$i + 1] ?? '';

            if ($value === '' && $nameData['optional']) {
                continue;
            }

            $cleanName = str_replace('*', '', $name);

            $result[$cleanName] = $exploded && str_contains($value, ',') ? explode(',', $value) : $value;
        }

        return $result;
    }

    public function __toString(): string
    {
        return $this->template;
    }

    private function validateLength(string $str, int $max, string $context): void
    {
        if (strlen($str) > $max) {
            throw new InvalidArgumentException(
                sprintf('%s exceeds maximum length of %d characters (got %d)', $context, $max, strlen($str))
            );
        }
    }

    /**
     * @return list<string|array{name: string, operator: string, names: list<string>, exploded: bool}>
     */
    private function parse(string $template): array
    {
        $parts = [];
        $currentText = '';
        $i = 0;
        $expressionCount = 0;

        while ($i < strlen($template)) {
            if ($template[$i] === '{') {
                if ($currentText !== '') {
                    $parts[] = $currentText;
                    $currentText = '';
                }

                $end = strpos($template, '}', $i);

                if ($end === false) {
                    throw new InvalidArgumentException('Unclosed template expression');
                }

                $expressionCount++;

                if ($expressionCount > self::MAX_TEMPLATE_EXPRESSIONS) {
                    throw new InvalidArgumentException(
                        sprintf('Template contains too many expressions (max %d)', self::MAX_TEMPLATE_EXPRESSIONS)
                    );
                }

                $expr = substr($template, $i + 1, $end - $i - 1);
                $operator = $this->getOperator($expr);
                $exploded = str_contains($expr, '*');
                $names = $this->getNames($expr);
                $name = $names[0] ?? '';

                foreach ($names as $varName) {
                    $this->validateLength($varName, self::MAX_VARIABLE_LENGTH, 'Variable name');
                }

                $parts[] = [
                    'name' => $name,
                    'operator' => $operator,
                    'names' => $names,
                    'exploded' => $exploded,
                ];

                $i = $end + 1;
            } else {
                $currentText .= $template[$i];
                $i++;
            }
        }

        if ($currentText !== '') {
            $parts[] = $currentText;
        }

        return $parts;
    }

    private function getOperator(string $expr): string
    {
        foreach (self::OPERATORS as $op) {
            if (str_starts_with($expr, $op)) {
                return $op;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function getNames(string $expr): array
    {
        $operator = $this->getOperator($expr);
        $withoutOperator = substr($expr, strlen($operator));

        $names = array_map(
            fn ($name): string => str_replace('*', '', trim($name)),
            explode(',', $withoutOperator)
        );

        return array_values(array_filter($names, fn ($name): bool => $name !== ''));
    }

    private function encodeValue(string $value): string
    {
        $this->validateLength($value, self::MAX_VARIABLE_LENGTH, 'Variable value');

        return rawurlencode($value);
    }

    /**
     * @param  array{name: string, operator: string, names: list<string>, exploded: bool}  $part
     * @param  array<string, string|list<string>>  $variables
     */
    private function expandPart(array $part, array $variables): string
    {
        if ($part['operator'] === '?' || $part['operator'] === '&') {
            $pairs = [];

            foreach ($part['names'] as $name) {
                $value = $variables[$name] ?? null;

                if ($value === null) {
                    continue;
                }

                $encoded = is_array($value)
                    ? implode(',', array_map($this->encodeValue(...), $value))
                    : $this->encodeValue((string) $value);

                $pairs[] = $name.'='.$encoded;
            }

            if ($pairs === []) {
                return '';
            }

            $separator = $part['operator'] === '?' ? '?' : '&';

            return $separator.implode('&', $pairs);
        }

        if (count($part['names']) > 1) {
            $values = [];

            foreach ($part['names'] as $name) {
                $value = $variables[$name] ?? null;

                if ($value !== null) {
                    $values[] = $value;
                }
            }

            if ($values === []) {
                return '';
            }

            return implode(',', array_map(fn ($v): string => is_array($v) ? $v[0] : $v, $values));
        }

        $value = $variables[$part['name']] ?? null;

        if ($value === null) {
            return '';
        }

        $values = is_array($value) ? $value : [$value];
        $encoded = array_map($this->encodeValue(...), $values);

        return match ($part['operator']) {
            '' => implode(',', $encoded),
            '+' => implode(',', $encoded),
            '#' => '#'.implode(',', $encoded),
            '.' => '.'.implode('.', $encoded),
            '/' => '/'.implode('/', $encoded),
            default => implode(',', $encoded),
        };
    }

    private function escapeRegExp(string $str): string
    {
        return preg_quote($str, '#');
    }

    /**
     * @param  array{name: string, operator: string, names: list<string>, exploded: bool}  $part
     * @return list<array{pattern: string, name: string, optional?: bool}>
     */
    private function partToRegExp(array $part): array
    {
        $patterns = [];

        foreach ($part['names'] as $varName) {
            $this->validateLength($varName, self::MAX_VARIABLE_LENGTH, 'Variable name');
        }

        if ($part['operator'] === '?' || $part['operator'] === '&') {
            foreach ($part['names'] as $i => $name) {
                $prefix = $i === 0 ? '[?&]' : '&';
                $patterns[] = [
                    'pattern' => '(?:'.$prefix.$this->escapeRegExp($name).'=([^&]*))?',
                    'name' => $name,
                    'optional' => true,
                ];
            }

            return $patterns;
        }

        $name = $part['name'];

        $pattern = match ($part['operator']) {
            '' => $part['exploded'] ? '([^/]+(?:,[^/]+)*)' : '([^/,]+)',
            '+', '#' => '(.+)',
            '.' => '\\.([^/,]+)',
            '/' => '/'.($part['exploded'] ? '([^/]+(?:,[^/]+)*)' : '([^/,]+)'),
            default => '([^/]+)',
        };

        $patterns[] = ['pattern' => $pattern, 'name' => $name];

        return $patterns;
    }
}
