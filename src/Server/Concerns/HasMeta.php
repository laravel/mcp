<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

use InvalidArgumentException;

trait HasMeta
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $meta = null;

    /**
     * @param  array<string, mixed>|string  $meta
     */
    public function setMeta(array|string $meta, mixed $value = null): void
    {
        $this->meta ??= [];

        if (! is_array($meta)) {
            if (is_null($value)) {
                throw new InvalidArgumentException('Value is required when using key-value signature.');
            }

            $this->meta[$meta] = $value;

            return;
        }

        $this->meta = array_merge($this->meta, $meta);
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param  T  $baseArray
     * @return T&array{_meta?: array<string, mixed>}
     */
    public function mergeMeta(array $baseArray): array
    {
        return ($meta = $this->meta)
            ? [...$baseArray, '_meta' => $meta]
            : $baseArray;
    }
}
