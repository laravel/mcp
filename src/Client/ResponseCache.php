<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Server;

class ResponseCache
{
    public const MAX_TTL_MS = 86_400_000;

    public function __construct(
        public readonly ?string $store = null,
        public readonly ?string $context = null,
    ) {
        //
    }

    /**
     * @param  Method<mixed>  $method
     * @param  Closure(): array<string, mixed>  $fetch
     * @return array<string, mixed>
     */
    public function remember(Method $method, Transport $transport, Closure $fetch): array
    {
        $params = $method->params();

        ksort($params);

        if (! $this->cacheable($method->method(), $params)) {
            return $fetch();
        }

        $recipe = $transport->recipe();
        $repository = Cache::store($this->store);
        $shared = $this->key($recipe, $method->method(), $params, CacheScope::Public);
        $private = $this->key($recipe, $method->method(), $params, CacheScope::Private);

        $found = $repository->many([$private, $shared]);
        $cached = $found[$private] ?? $found[$shared];

        if (is_array($cached)) {
            return $cached;
        }

        $result = $fetch();

        if (Arr::get($result, 'resultType', 'complete') !== 'complete' || filled(Arr::get($result, 'nextCursor'))) {
            return $result;
        }

        $ttlMs = min((int) (Arr::get($result, 'ttlMs') ?? 0), self::MAX_TTL_MS);

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
            && ! isset($params['cursor'])
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
            $scope === CacheScope::Public
                ? 'public'
                : $this->hash([$this->context, Arr::except($recipe, ['timeoutSeconds'])]),
            $method,
            $this->hash($params),
        ]);
    }

    protected function hash(mixed $value): string
    {
        return hash('sha256', serialize($value));
    }
}
