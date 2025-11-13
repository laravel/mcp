<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

trait HasMeta
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $meta = null;

    /**
     * @param  string|array<string, mixed>  $meta
     */
    public function setMeta(string|array $meta, mixed $value = null): void
    {
        $this->meta ??= [];

        if (! is_array($meta)) {
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
