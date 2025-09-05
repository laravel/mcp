<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Traits\InteractsWithData;

/**
 * @implements Arrayable<string, mixed>
 */
class Request implements Arrayable
{
    use InteractsWithData;

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        protected array $arguments = [],
    ) {
        //
    }

    /**
     * Retrieve all data from the instance.
     *
     * @param  array<array-key, string>|array-key|null  $keys
     * @return array<string, mixed>
     */
    public function all(mixed $keys = null): array
    {
        if (is_null($keys)) {
            return $this->arguments;
        }

        return array_intersect_key($this->arguments, array_flip(is_array($keys) ? $keys : func_get_args()));
    }

    /**
     * Retrieve data from the instance.
     */
    protected function data(mixed $key = null, mixed $default = null)
    {
        if (is_null($key)) {
            return $this->arguments;
        }

        return $this->arguments[$key] ?? $default;
    }

    /**
     * Get an argument by key.
     */
    public function get(string $key): mixed
    {
        return $this->arguments[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->arguments;
    }
}
