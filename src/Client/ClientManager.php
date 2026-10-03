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
        $client = match (true) {
            isset($this->factories[$name]) => ($this->factories[$name])(),
            is_array(config("mcp.clients.{$name}")) => $this->fromConfig("mcp.clients.{$name}"),
            default => throw new ClientException("MCP client [{$name}] has not been registered."),
        };

        return $client->setName($name);
    }

    protected function fromConfig(string $key): Client
    {
        $config = config();

        if (filled($config->get("{$key}.url"))) {
            $client = Client::web($config->string("{$key}.url"))
                ->withHeaders(array_filter($config->array("{$key}.headers", []), filled(...)));

            if (filled($config->get("{$key}.token"))) {
                $client->withToken($config->string("{$key}.token"));
            }
        } else {
            $client = Client::local($config->string("{$key}.command"), $config->array("{$key}.args", []));
        }

        if (is_numeric($timeout = $config->get("{$key}.timeout"))) {
            $client->withTimeout((float) $timeout);
        }

        return $client
            ->onlyTools($config->get("{$key}.tools.only"))
            ->exceptTools($config->array("{$key}.tools.except", []));
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
