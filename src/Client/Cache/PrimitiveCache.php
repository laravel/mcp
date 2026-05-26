<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Cache;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository;
use Laravel\Mcp\Exceptions\ClientException;
use Throwable;

class PrimitiveCache
{
    /**
     * @param  ?Closure(): (string|int|Authenticatable|null)  $scope
     */
    public function __construct(
        protected Repository $cache,
        protected string $name,
        protected int $ttl,
        protected ?Closure $scope = null,
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
        return "mcp-list:{$this->name}:{$kind}{$this->scopeSegment()}";
    }

    protected function scopeSegment(): string
    {
        if (! $this->scope instanceof Closure) {
            return '';
        }

        $value = ($this->scope)();

        if ($value instanceof Authenticatable) {
            $value = $value->getAuthIdentifier();
        }

        if ($value === null || $value === '') {
            return '';
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new ClientException(sprintf(
                'MCP cache scope closure must return string|int|Authenticatable|null, got %s.',
                get_debug_type($value),
            ));
        }

        return ":scope:{$value}";
    }
}
