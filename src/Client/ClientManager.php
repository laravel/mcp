<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Mcp\Client;
use Laravel\Mcp\Exceptions\ClientException;

class ClientManager
{
    public const DEFAULT_CACHE_TTL = 3600;

    /** @var array<string, Closure(): Client> */
    protected array $factories = [];

    /** @var array<string, Client> */
    protected array $clients = [];

    /**
     * @param  Closure(): Client  $factory
     * @param  ?Closure(): (string|int|Authenticatable|null)  $scope
     */
    public function registerClient(
        string $name,
        Closure $factory,
        int|false $cache = self::DEFAULT_CACHE_TTL,
        ?Closure $scope = null,
    ): void {
        if (isset($this->clients[$name])) {
            try {
                $this->clients[$name]->disconnect();
            } catch (ClientException) {
            }

            unset($this->clients[$name]);
        }

        $this->factories[$name] = fn (): Client => $factory()->asRegisteredClient($name, $cache, $scope);
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
}
