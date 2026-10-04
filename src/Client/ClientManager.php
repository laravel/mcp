<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Macroable;
use Laravel\Mcp\Client;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\WebClient;

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
            is_array(config("mcp.clients.{$name}")) => $this->fromConfig($name),
            default => throw new ClientException("MCP client [{$name}] has not been registered."),
        };

        return $client->setName($name);
    }

    protected function fromConfig(string $name): Client
    {
        $config = config();
        $key = "mcp.clients.{$name}";

        if (filled($config->get("{$key}.url"))) {
            $client = $this->webClientFromConfig($key);
        } elseif (filled($config->get("{$key}.command"))) {
            $client = Client::local(
                $config->string("{$key}.command"),
                $config->array("{$key}.args", []),
                array_filter($config->array("{$key}.env", []), filled(...)),
            );
        } else {
            throw new ClientException("MCP client [{$name}] must define a [url] or [command].");
        }

        if (is_numeric($timeout = $config->get("{$key}.timeout"))) {
            $client->withTimeout((float) $timeout);
        }

        if ($cache = $config->get("{$key}.cache")) {
            $client->withCache(...is_array($cache) ? Arr::only($cache, ['store', 'for']) : []);
        }

        if (filled($version = $config->get("{$key}.protocol_version"))) {
            $client->withProtocolVersion(ProtocolVersion::from($version));
        }

        return $client
            ->onlyTools($config->get("{$key}.tools.only"))
            ->exceptTools($config->array("{$key}.tools.except", []));
    }

    protected function webClientFromConfig(string $key): WebClient
    {
        $config = config();

        $client = Client::web($config->string("{$key}.url"))
            ->withHeaders(array_filter($config->array("{$key}.headers", []), filled(...)));

        if (filled($config->get("{$key}.token"))) {
            $client->withToken($config->string("{$key}.token"));
        }

        if (is_array($config->get("{$key}.oauth"))) {
            $client->withOAuth(
                clientId: $config->get("{$key}.oauth.client_id"),
                clientSecret: $config->get("{$key}.oauth.client_secret"),
                scope: $config->get("{$key}.oauth.scope"),
                redirectUri: $config->get("{$key}.oauth.redirect_uri"),
            );
        }

        return $client;
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
