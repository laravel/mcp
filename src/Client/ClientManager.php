<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Traits\Macroable;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Cache\PrimitiveCache;
use Laravel\Mcp\Exceptions\ClientException;

class ClientManager
{
    use Macroable;

    /** @var array<string, Closure(): Client> */
    protected array $factories = [];

    /** @var array<string, Client> */
    protected array $clients = [];

    public function __construct(protected ?Repository $cacheRepository = null)
    {
        //
    }

    /**
     * @param  Closure(): Client  $factory
     * @param  ?Closure(): (string|int|Authenticatable|null)  $scope
     */
    public function registerClient(
        string $name,
        Closure $factory,
        ?int $cacheTtl = null,
        ?Closure $scope = null,
    ): void {
        $cacheTtl ??= (int) config('mcp.client.cache_ttl', 3600);

        if (isset($this->clients[$name])) {
            try {
                $this->clients[$name]->disconnect();
            } catch (ClientException) {
            }

            unset($this->clients[$name]);
        }

        $this->factories[$name] = fn (): Client => $factory()
            ->asRegisteredClient($name, $cacheTtl, $scope)
            ->withListCache($this->buildListCache($name, $cacheTtl, $scope));
    }

    public function client(string $name): Client
    {
        if (! array_key_exists($name, $this->factories)) {
            throw new ClientException("MCP client [{$name}] has not been registered.");
        }

        return $this->clients[$name] ??= ($this->factories[$name])();
    }

    public function disconnectAll(): void
    {
        foreach ($this->clients as $client) {
            try {
                $client->disconnect();
            } catch (ClientException) {
            }
        }

        $this->clients = [];
    }

    /**
     * @param  ?Closure(): (string|int|Authenticatable|null)  $scope
     */
    protected function buildListCache(string $name, int $cacheTtl, ?Closure $scope): ?PrimitiveCache
    {
        if ($cacheTtl <= 0 || ! $this->cacheRepository instanceof Repository) {
            return null;
        }

        return new PrimitiveCache(
            cache: $this->cacheRepository,
            name: $name,
            ttl: $cacheTtl,
            scope: $scope,
        );
    }
}
