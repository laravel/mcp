<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Throwable;

class PrimitiveCache
{
    public function __construct(
        protected Repository $cache,
        protected string $name,
        protected int $ttl,
    ) {}

    /**
     * @param  Closure(): array<int, array<string, mixed>>  $fetch
     * @return array<int, array<string, mixed>>
     */
    public function remember(string $kind, Closure $fetch): array
    {
        $key = $this->key($kind);

        try {
            $cached = $this->cache->get($key);
        } catch (Throwable) {
            $cached = null;
        }

        if (is_array($cached)) {
            /** @var array<int, array<string, mixed>> $cached */
            return $cached;
        }

        $payloads = $fetch();

        try {
            $this->cache->put($key, $payloads, $this->ttl);
        } catch (Throwable) {
        }

        return $payloads;
    }

    public function flush(): void
    {
        try {
            $this->cache->forget($this->key('tools'));
        } catch (Throwable) {
        }
    }

    protected function key(string $kind): string
    {
        return "mcp-list:{$this->name}:{$kind}";
    }
}
