<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Cache;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository;
use Laravel\Mcp\Exceptions\ClientException;

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
     * @return array<int, mixed>|null
     */
    public function get(string $kind): ?array
    {
        $value = $this->cache->get($this->key($kind));

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     */
    public function put(string $kind, array $payloads): void
    {
        $this->cache->put($this->key($kind), $payloads, $this->ttl);
    }

    public function flush(): void
    {
        $this->cache->forget($this->key('tools'));
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
