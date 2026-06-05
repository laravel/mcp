<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Closure;
use Illuminate\Support\Traits\Macroable;
use Laravel\Mcp\Client;
use Laravel\Mcp\Exceptions\ClientException;

class ClientManager
{
    use Macroable;

    /** @var array<string, Closure(): Client> */
    protected array $factories = [];

    /** @var array<string, Client> */
    protected array $clients = [];

    /**
     * @param  Closure(): Client  $factory
     */
    public function registerClient(string $name, Closure $factory): void
    {
        if (isset($this->clients[$name])) {
            $this->disconnect($this->clients[$name]);

            unset($this->clients[$name]);
        }

        $this->factories[$name] = $factory;
    }

    public function client(string $name): Client
    {
        return $this->clients[$name] ??= $this->build($name);
    }

    public function build(string $name): Client
    {
        if (! array_key_exists($name, $this->factories)) {
            throw new ClientException("MCP client [{$name}] has not been registered.");
        }

        return ($this->factories[$name])()->setName($name);
    }

    public function disconnectAll(): void
    {
        foreach ($this->clients as $client) {
            $this->disconnect($client);
        }

        $this->clients = [];
    }

    protected function disconnect(Client $client): void
    {
        try {
            $client->disconnect();
        } catch (ClientException) {
        }
    }
}
