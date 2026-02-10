<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use InvalidArgumentException;
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

        $transport = $this->createTransport($config);

        $client = new Client(
            transport: $transport,
            name: $name,
            cacheTtl: isset($config['cache_ttl']) ? (int) $config['cache_ttl'] : null,
            protocolVersion: (string) config('mcp.protocol_version', '2025-11-25'),
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
    public function createTransport(array $config): ClientTransport
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
            ),
            default => throw new InvalidArgumentException("Unsupported MCP transport [{$transport}]."),
        };
    }
}
