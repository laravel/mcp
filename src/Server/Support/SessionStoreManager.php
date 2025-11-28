<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Support;

use Illuminate\Contracts\Cache\Repository as Cache;

class SessionStoreManager
{
    protected const PREFIX = 'mcp';

    public function __construct(
        protected Cache $cache,
        protected ?string $sessionId = null,
        protected ?int $ttl = null,
    ) {
        $this->ttl ??= config('mcp.session_ttl', 86400);
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->sessionId === null) {
            return;
        }

        $this->cache->put($this->cacheKey($key), $value, $this->ttl);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->sessionId === null) {
            return $default;
        }

        return $this->cache->get($this->cacheKey($key), $default);
    }

    public function has(string $key): bool
    {
        if ($this->sessionId === null) {
            return false;
        }

        return $this->cache->has($this->cacheKey($key));
    }

    public function forget(string $key): void
    {
        if ($this->sessionId === null) {
            return;
        }

        $this->cache->forget($this->cacheKey($key));
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    protected function cacheKey(string $key): string
    {
        return self::PREFIX.":{$this->sessionId}:{$key}";
    }
}
