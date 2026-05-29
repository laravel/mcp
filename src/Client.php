<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Support\Collection;
use Laravel\Mcp\Client\Cache\PrimitiveCache;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Methods\Ping;
use Laravel\Mcp\Client\Methods\Tools\CallTool;
use Laravel\Mcp\Client\Methods\Tools\ListTools;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Client\Schema\InitializeResult;
use Laravel\Mcp\Client\Schema\ToolResult;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Client\Transport\StdioTransport;
use Laravel\Mcp\Schema\Implementation;

class Client
{
    public Implementation $clientInfo;

    protected Protocol $protocol;

    protected ?PrimitiveCache $listCache = null;

    public function __construct(
        protected Transport $transport,
        ?Implementation $clientInfo = null,
    ) {
        $this->clientInfo = $clientInfo ?? new Implementation(
            name: config('app.name', 'Laravel MCP Client'),
            version: '0.0.1',
        );

        $this->protocol = new Protocol($this->transport, $this->clientInfo);
    }

    /**
     * @param  array<int, string>  $args
     */
    public static function local(string $command, array $args = []): static
    {
        return new static(new StdioTransport($command, $args));
    }

    public static function web(string $url): WebClient
    {
        return new WebClient(new HttpTransport($url));
    }

    public function withTimeout(float $seconds): static
    {
        $this->transport->setTimeoutSeconds($seconds);

        return $this;
    }

    /**
     * @internal Used by ClientManager when wiring registered clients.
     */
    public function withListCache(?PrimitiveCache $cache): static
    {
        $this->listCache = $cache;

        return $this;
    }

    public function connect(): static
    {
        $this->protocol->connect();

        return $this;
    }

    public function disconnect(): void
    {
        $this->protocol->disconnect();
    }

    public function connected(): bool
    {
        return $this->protocol->connected();
    }

    public function initializeResult(): ?InitializeResult
    {
        return $this->protocol->initializeResult();
    }

    public function ping(): void
    {
        (new Ping)->handle($this->protocol);
    }

    /**
     * @return Collection<string, Tool>
     */
    public function tools(?int $limit = null): Collection
    {
        return (new ListTools($this, $this->listCache, limit: $limit))->handle($this->protocol);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function callTool(string $name, array $arguments = []): ToolResult
    {
        return (new CallTool($name, $arguments))->handle($this->protocol);
    }

    public function flushCache(): void
    {
        $this->listCache?->flushAll();
    }

    public function __destruct()
    {
        if ($this->connected()) {
            $this->disconnect();
        }
    }
}
