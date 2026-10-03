<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Closure;
use Illuminate\Support\Arr;
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
        $config = config("mcp.clients.{$name}");

        $client = match (true) {
            isset($this->factories[$name]) => ($this->factories[$name])(),
            is_array($config) => $this->fromConfig($config),
            default => throw new ClientException("MCP client [{$name}] has not been registered."),
        };

        return $client->setName($name);
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    protected function fromConfig(array $config): Client
    {
        if (filled($config['url'] ?? null)) {
            $client = Client::web(Arr::string($config, 'url'))
                ->withHeaders(array_filter(Arr::array($config, 'headers', []), filled(...)));

            if (filled($config['token'] ?? null)) {
                $client->withToken(Arr::string($config, 'token'));
            }
        } else {
            $client = Client::local(Arr::string($config, 'command'), Arr::array($config, 'args', []));
        }

        if (is_numeric($config['timeout'] ?? null)) {
            $client->withTimeout((float) $config['timeout']);
        }

        return $client
            ->onlyTools(Arr::get($config, 'tools.only'))
            ->exceptTools(Arr::array($config, 'tools.except', []));
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
