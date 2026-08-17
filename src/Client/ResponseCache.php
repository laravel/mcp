<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Server;

class ResponseCache
{
    public function __construct(
        public readonly ?string $store = null,
        public readonly ?string $context = null,
    ) {
        //
    }

    /**
     * @param  Method<mixed>  $method
     * @param  Closure(): array<string, mixed>  $recipe
     * @param  Closure(): array<string, mixed>  $fetch
     * @return array<string, mixed>
     */
    public function remember(Method $method, Closure $recipe, Closure $fetch): array
    {
        $params = $method->params();

        if (! $this->cacheable($method->method(), $params)) {
            return $fetch();
        }

        $resolved = $recipe();
        $repository = Cache::store($this->store);
        $shared = $this->key($resolved, $method->method(), $params, CacheScope::Public);
        $private = $this->key($resolved, $method->method(), $params, CacheScope::Private);

        $cached = $repository->get($private) ?? $repository->get($shared);

        if (is_array($cached)) {
            return $cached;
        }

        $result = $fetch();
        $ttlMs = (int) (Arr::get($result, 'ttlMs') ?? 0);

        if ($ttlMs > 0) {
            $repository->put(
                Arr::get($result, 'cacheScope') === CacheScope::Public->value ? $shared : $private,
                $result,
                now()->addMilliseconds($ttlMs),
            );
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function cacheable(string $method, array $params): bool
    {
        return in_array($method, Server::CACHEABLE_METHODS, true)
            && ! isset($params['inputResponses'])
            && ! isset($params['requestState']);
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @param  array<string, mixed>  $params
     */
    protected function key(array $recipe, string $method, array $params, CacheScope $scope): string
    {
        return implode(':', [
            'mcp',
            $this->hash(Arr::only($recipe, ['driver', 'url', 'command', 'args', 'headers'])),
            $scope === CacheScope::Public ? 'public' : ($this->context ?? $this->hash(Arr::except($recipe, ['timeoutSeconds']))),
            $method,
            $this->hash($params),
        ]);
    }

    protected function hash(mixed $value): string
    {
        return hash('xxh128', (string) json_encode($value));
    }
}
