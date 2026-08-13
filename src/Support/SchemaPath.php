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

    public function __toString(): string
    {
        return collect($this->segments)
            ->map(fn (string $segment): string => '/'.str_replace(['~', '/'], ['~0', '~1'], $segment))
            ->implode('');
    }
}
