<?php

declare(strict_types=1);

namespace Laravel\Mcp\Support;

use Stringable;

class SchemaPath implements Stringable
{
    /**
     * @param  list<string>  $segments
     */
    public function __construct(public readonly array $segments = [])
    {
        //
    }

    public static function root(): self
    {
        return new self;
    }

    public static function fromPointer(string $pointer): self
    {
        if (! str_starts_with($pointer, '/')) {
            return new self([$pointer]);
        }

        $segments = array_map(
            fn (string $segment): string => str_replace(['~1', '~0'], ['/', '~'], $segment),
            explode('/', substr($pointer, 1)),
        );

        return new self(array_values($segments));
    }

    public function child(string $segment): self
    {
        return new self([...$this->segments, $segment]);
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public function valueIn(array $arguments): mixed
    {
        $value = $arguments;

        foreach ($this->segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function annotate(array $schema, string $key, string $value): array
    {
        if ($this->segments === []) {
            return $schema;
        }

        [$segment, $remaining] = [$this->segments[0], new self(array_slice($this->segments, 1))];

        $property = $schema['properties'][$segment] ?? null;

        if (! is_array($property)) {
            return $schema;
        }

        $schema['properties'][$segment] = $remaining->segments === []
            ? [...$property, $key => $value]
            : $remaining->annotate($property, $key, $value);

        return $schema;
    }

    public function __toString(): string
    {
        return collect($this->segments)
            ->map(fn (string $segment): string => '/'.str_replace(['~', '/'], ['~0', '~1'], $segment))
            ->implode('');
    }
}
