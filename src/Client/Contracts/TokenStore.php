<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Contracts;

use Closure;

interface TokenStore
{
    /**
     * @return ?array<string, mixed>
     */
    public function get(string $key): ?array;

    /**
     * @return ?array<string, mixed>
     */
    public function pull(string $key): ?array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $key, array $data, ?int $ttlSeconds = null): void;

    public function forget(string $key): void;

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function lock(string $key, Closure $work): mixed;
}
