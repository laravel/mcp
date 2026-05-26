<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Closure;
use Laravel\Mcp\Client;
use Laravel\Mcp\Exceptions\ClientException;
use Throwable;

class ClientManager
{
    public const DEFAULT_CACHE_TTL = 3600;

    /** @var array<string, Closure(): Client> */
    protected array $factories = [];

    /** @var array<string, Client> */
    protected array $resolved = [];

    /**
     * @param  Closure(): Client  $factory
     */
    public function registerClientFor(string $name, Closure $factory, int|false $cache = self::DEFAULT_CACHE_TTL): void
    {
        ($this->resolved[$name] ?? null)?->disconnect();

        unset($this->resolved[$name]);

        $this->factories[$name] = fn (): Client => $factory()->asRegisteredClient($name, $cache);
    }

    public function client(string $name): Client
    {
        if (! array_key_exists($name, $this->factories)) {
            throw new ClientException("MCP client [{$name}] has not been registered.");
        }

        return $this->resolved[$name] ??= ($this->factories[$name])();
    }

    public function disconnectAll(): void
    {
        foreach ($this->resolved as $client) {
            try {
                $client->disconnect();
            } catch (Throwable) {
            }
        }

        $this->resolved = [];
    }
}
