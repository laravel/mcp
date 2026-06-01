<?php

declare(strict_types=1);

namespace Tests\Fixtures\Client\Auth;

use Closure;
use Laravel\Mcp\Client\Contracts\TokenStore;

final class InMemoryTokenStore implements TokenStore
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public array $entries = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    public function get(string $key): ?array
    {
        $this->reads[] = $key;

        return $this->entries[$key] ?? null;
    }

    public function pull(string $key): ?array
    {
        $data = $this->get($key);

        $this->forget($key);

        return $data;
    }

    public function put(string $key, array $data, ?int $ttlSeconds = null): void
    {
        $this->entries[$key] = $data;
    }

    public function forget(string $key): void
    {
        unset($this->entries[$key]);
    }

    public function lock(string $key, Closure $work): mixed
    {
        return $work();
    }
}
