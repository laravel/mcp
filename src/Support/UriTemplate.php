<?php

declare(strict_types=1);

namespace Laravel\Mcp\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
                $pattern .= preg_quote($part, '#');

                continue;
            }

            foreach ($this->partToRegExp($part) as $patternData) {
                $pattern .= $patternData['pattern'];
                $names[] = [
                    'name' => $patternData['name'],
                    'exploded' => $part['exploded'],
                    'optional' => $patternData['optional'] ?? false,
                ];
            }
        }

        $pattern .= '$';

        $this->validateLength($pattern, self::MAX_REGEX_LENGTH, 'Generated regex pattern');

        if (! preg_match('#'.$pattern.'#', $uri, $matches)) {
            return null;
        }

        return collect($names)
            ->mapWithKeys(function (array $nameData, int $i) use ($matches): array {
                $value = $matches[$i + 1] ?? '';

                if ($value === '' && $nameData['optional']) {
                    return [];
                }

                $cleanName = Str::remove('*', $nameData['name']);
                $parsed = $nameData['exploded'] && Str::contains($value, ',')
                    ? explode(',', $value)
                    : $value;

                return [$cleanName => $parsed];
            })
            ->all();
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
            sprintf('%s exceeds maximum length of %d characters (got %d)', $context, $max, Str::length($str))
        );
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
        $length = Str::length($template);

        while ($i < $length) {
            if ($template[$i] !== '{') {
                $currentText .= $template[$i];
                $i++;

                continue;
            }

            if ($currentText !== '') {
                $parts[] = $currentText;
                $currentText = '';
            }

            $end = strpos($template, '}', $i);

            throw_if($end === false, InvalidArgumentException::class, 'Unclosed template expression');

            $expressionCount++;

            throw_if(
                $expressionCount > self::MAX_TEMPLATE_EXPRESSIONS,
                InvalidArgumentException::class,
                sprintf('Template contains too many expressions (max %d)', self::MAX_TEMPLATE_EXPRESSIONS)
            );

            $expr = Str::substr($template, $i + 1, $end - $i - 1);
            $operator = $this->getOperator($expr);
            $names = $this->getNames($expr);

            collect($names)->each(fn (string $varName) => $this->validateLength($varName, self::MAX_VARIABLE_LENGTH, 'Variable name'));

            $parts[] = [
                'name' => Arr::first($names, default: ''),
                'operator' => $operator,
                'names' => $names,
                'exploded' => Str::contains($expr, '*'),
            ];

            $i = $end + 1;
        }

        if ($currentText !== '') {
            $parts[] = $currentText;
        }

        return $parts;
    }

    private function getOperator(string $expr): string
    {
        return Arr::first(
            self::OPERATORS,
            fn (string $op): bool => Str::startsWith($expr, $op),
            ''
        );
    }

    /**
     * @return list<string>
     */
    private function getNames(string $expr): array
    {
        $operator = $this->getOperator($expr);

        return array_values(
            Str::of($expr)
                ->substr(Str::length($operator))
                ->explode(',')
                ->map(fn (string $name): string => Str::remove('*', trim($name)))
                ->filter(fn (string $name): bool => filled($name))
                ->values()
                ->all()
        );
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
        if (in_array($part['operator'], ['?', '&'], true)) {
            $pairs = collect($part['names'])
                ->map(fn (string $name): ?string => match (true) {
                    ! Arr::has($variables, $name) => null,
                    is_array($variables[$name]) => $name.'='.collect($variables[$name])->map($this->encodeValue(...))->implode(','),
                    default => $name.'='.$this->encodeValue((string) $variables[$name]),
                })
                ->filter()
                ->values()
                ->all();

            if (count($pairs) === 0) {
                return '';
            }

            return ($part['operator'] === '?' ? '?' : '&').implode('&', $pairs);
        }

        if (count($part['names']) > 1) {
            $values = collect($part['names'])
                ->map(fn (string $name): mixed => $variables[$name] ?? null)
                ->filter(fn (mixed $value): bool => $value !== null)
                ->map(fn (mixed $v): string => is_array($v) ? $v[0] : (string) $v)
                ->all();

            return count($values) === 0 ? '' : implode(',', $values);
        }

        $value = $variables[$part['name']] ?? null;

        if ($value === null) {
            return '';
        }

        $encoded = collect(Arr::wrap($value))->map($this->encodeValue(...));

        return match ($part['operator']) {
            '', '+' => $encoded->implode(','),
            '#' => '#'.$encoded->implode(','),
            '.' => '.'.$encoded->implode('.'),
            '/' => '/'.$encoded->implode('/'),
            default => $encoded->implode(','),
        };
    }

    /**
     * @param  array{name: string, operator: string, names: list<string>, exploded: bool}  $part
     * @return list<array{pattern: string, name: string, optional?: bool}>
     */
    private function partToRegExp(array $part): array
    {
        collect($part['names'])->each(
            fn (string $varName) => $this->validateLength($varName, self::MAX_VARIABLE_LENGTH, 'Variable name')
        );

        if (in_array($part['operator'], ['?', '&'], true)) {
            return array_values(
                collect($part['names'])
                    ->map(fn (string $name, int $i): array => [
                        'pattern' => '(?:'.($i === 0 ? '[?&]' : '&').preg_quote($name, '#').'=([^&]*))?',
                        'name' => $name,
                        'optional' => true,
                    ])
                    ->all()
            );
        }

        $pattern = match ($part['operator']) {
            '' => $part['exploded'] ? '([^/]+(?:,[^/]+)*)' : '([^/,]+)',
            '+', '#' => '(.+)',
            '.' => '\\.([^/,]+)',
            '/' => '/'.($part['exploded'] ? '([^/]+(?:,[^/]+)*)' : '([^/,]+)'),
            default => '([^/]+)',
        };

        return [['pattern' => $pattern, 'name' => $part['name']]];
    }
}
