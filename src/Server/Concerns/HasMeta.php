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
     * Set meta data, supporting both array and key-value signatures.
     * Multiple calls will merge the meta data.
     *
     * @param  string|array<string, mixed>  $meta
     */
    public function setMeta(string|array $meta, mixed $value = null): void
    {
        if (is_array($meta)) {
            $this->meta = $this->meta
                ? array_merge($this->meta, $meta)
                : $meta;
        } else {
            $this->meta = $this->meta
                ? [...$this->meta, $meta => $value]
                : [$meta => $value];
        }
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param  T  $baseArray
     * @return T&array{_meta?: array<string, mixed>}
     */
    protected function withMeta(array $baseArray): array
    {
        return ($meta = $this->meta)
            ? [...$baseArray, '_meta' => $meta]
            : $baseArray;
    }
}
