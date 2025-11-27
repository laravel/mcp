<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Store;

use Illuminate\Contracts\Cache\Repository as Cache;

class SessionStoreManager
{
    protected const PREFIX = 'mcp';

    protected const TTL = 3600;

    public function __construct(
        protected Cache $cache,
        protected ?string $sessionId = null,
    ) {
        //
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->sessionId === null) {
            return;
        }

        $this->cache->put($this->cacheKey($key), $value, self::TTL);
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
