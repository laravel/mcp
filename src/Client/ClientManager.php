<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use InvalidArgumentException;
use Laravel\Mcp\Client\Auth\AuthorizationCodeProvider;
use Laravel\Mcp\Client\Auth\AuthProvider;
use Laravel\Mcp\Client\Auth\ClientCredentialsProvider;
use Laravel\Mcp\Client\Contracts\ClientTransport;
use Laravel\Mcp\Client\Transport\HttpClientTransport;
use Laravel\Mcp\Client\Transport\StdioClientTransport;

class ClientManager
{
    /** @var array<string, Client> */
    protected array $clients = [];

    public function client(string $name): Client
    {
        if (isset($this->clients[$name])) {
            return $this->clients[$name];
        }

        /** @var array<string, mixed> $config */
        $config = config("mcp.servers.{$name}");

        if (empty($config)) {
            throw new InvalidArgumentException("MCP server [{$name}] is not configured.");
        }

        $transport = $this->createTransport($config, $name);

        $client = new Client(
            transport: $transport,
            name: $name,
            cacheTtl: isset($config['cache_ttl']) ? (int) $config['cache_ttl'] : null,
            protocolVersion: (string) config('mcp.protocol_version', '2025-11-25'),
            capabilities: (array) ($config['capabilities'] ?? []),
        );

        $client->connect();

        return $this->clients[$name] = $client;
    }

    public function purge(?string $name = null): void
    {
        if ($name !== null) {
            if (isset($this->clients[$name])) {
                $this->clients[$name]->disconnect();
                unset($this->clients[$name]);
            }

            return;
        }

        foreach ($this->clients as $client) {
            $client->disconnect();
        }

        $this->clients = [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function createTransport(array $config, string $name = ''): ClientTransport
    {
        $transport = $config['transport'] ?? 'stdio';

        return match ($transport) {
            'stdio' => new StdioClientTransport(
                command: (string) ($config['command'] ?? ''),
                args: (array) ($config['args'] ?? []),
                workingDirectory: isset($config['working_directory']) ? (string) $config['working_directory'] : null,
                env: (array) ($config['env'] ?? []),
                timeout: (float) ($config['timeout'] ?? 30),
            ),
            'http' => new HttpClientTransport(
                url: (string) ($config['url'] ?? ''),
                headers: (array) ($config['headers'] ?? []),
                timeout: (float) ($config['timeout'] ?? 30),
                authProvider: isset($config['auth']) ? $this->createAuthProvider((array) $config['auth'], $name) : null,
            ),
            default => throw new InvalidArgumentException("Unsupported MCP transport [{$transport}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function createAuthProvider(array $config, string $name): AuthProvider
    {
        $type = $config['type'] ?? null;

        return match ($type) {
            'client_credentials' => new ClientCredentialsProvider(
                clientId: (string) ($config['client_id'] ?? ''),
                clientSecret: (string) ($config['client_secret'] ?? ''),
                serverName: $name,
                tokenEndpoint: isset($config['token_endpoint']) ? (string) $config['token_endpoint'] : null,
                scope: (string) ($config['scope'] ?? 'mcp:use'),
            ),
            'authorization_code' => new AuthorizationCodeProvider(
                clientId: (string) ($config['client_id'] ?? ''),
                redirectUri: (string) ($config['redirect_uri'] ?? ''),
                serverName: $name,
                authorizationEndpoint: isset($config['authorization_endpoint']) ? (string) $config['authorization_endpoint'] : null,
                tokenEndpoint: isset($config['token_endpoint']) ? (string) $config['token_endpoint'] : null,
                scope: (string) ($config['scope'] ?? 'mcp:use'),
            ),
            default => throw new InvalidArgumentException("Unsupported MCP auth type [{$type}]."),
        };
    }
}
