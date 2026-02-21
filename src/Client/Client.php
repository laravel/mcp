<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Client\Contracts\ClientTransport;
use Laravel\Mcp\Client\Methods\CallTool;
use Laravel\Mcp\Client\Methods\Initialize;
use Laravel\Mcp\Client\Methods\ListTools;
use Laravel\Mcp\Client\Methods\Ping;

class Client
{
    /** @var array<string, mixed>|null */
    protected ?array $serverInfo = null;

    /** @var array<string, mixed>|null */
    protected ?array $serverCapabilities = null;

    protected bool $initialized = false;

    protected ClientContext $context;

    /** @var array<string, class-string<\Laravel\Mcp\Client\Contracts\ClientMethod>> */
    protected array $methods = [
        'initialize' => Initialize::class,
        'tools/list' => ListTools::class,
        'tools/call' => CallTool::class,
        'ping' => Ping::class,
    ];

    /**
     * @param  array<string, mixed>  $capabilities
     */
    public function __construct(
        protected ClientTransport $transport,
        protected string $name = 'laravel-mcp-client',
        protected ?int $cacheTtl = null,
        protected string $protocolVersion = '2025-11-25',
        protected array $capabilities = [],
    ) {
        $this->context = new ClientContext($transport, $this->name, $this->protocolVersion, $this->capabilities);
    }

    public function connect(): static
    {
        $this->transport->connect();

        $this->initialize();

        return $this;
    }

    public function disconnect(): void
    {
        if ($this->initialized) {
            $this->initialized = false;
            $this->serverInfo = null;
            $this->serverCapabilities = null;
            $this->context->resetRequestId();
        }

        $this->transport->disconnect();
    }

    /**
     * @return Collection<int, ClientTool>
     */
    public function tools(): Collection
    {
        $cacheKey = "mcp-client:{$this->name}:tools";

        if ($this->cacheTtl !== null && Cache::has($cacheKey)) {
            /** @var Collection<int, ClientTool> */
            return Cache::get($cacheKey);
        }

        $allTools = [];
        $cursor = null;

        do {
            $params = $cursor !== null ? ['cursor' => $cursor] : [];
            $result = $this->callMethod('tools/list', $params);
            array_push($allTools, ...($result['tools'] ?? []));
            $cursor = $result['nextCursor'] ?? null;
        } while ($cursor !== null);

        /** @var Collection<int, ClientTool> $tools */
        $tools = collect($allTools)->map(
            fn (array $definition): ClientTool => ClientTool::fromArray($definition, $this)
        );

        if ($this->cacheTtl !== null) {
            Cache::put($cacheKey, $tools, $this->cacheTtl);
        }

        return $tools;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function callTool(string $name, array $arguments = []): array
    {
        return $this->callMethod('tools/call', [
            'name' => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function serverInfo(): ?array
    {
        return $this->serverInfo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function serverCapabilities(): ?array
    {
        return $this->serverCapabilities;
    }

    public function isConnected(): bool
    {
        return $this->initialized && $this->transport->isConnected();
    }

    public function ping(): void
    {
        $this->callMethod('ping');
    }

    public function clearCache(): void
    {
        Cache::forget("mcp-client:{$this->name}:tools");
    }

    protected function initialize(): void
    {
        $result = $this->callMethod('initialize');

        $this->serverInfo = $result['serverInfo'] ?? null;
        $this->serverCapabilities = $result['capabilities'] ?? null;

        $this->initialized = true;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function callMethod(string $method, array $params = []): array
    {
        $handler = new $this->methods[$method];

        return $handler->handle($this->context, $params);
    }
}
