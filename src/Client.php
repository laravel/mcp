<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Laravel\Mcp\Client\ClientManager;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Enums\ProtocolEra;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\Methods\Ping;
use Laravel\Mcp\Client\Methods\Prompts\GetPrompt;
use Laravel\Mcp\Client\Methods\Prompts\ListPrompts;
use Laravel\Mcp\Client\Methods\Resources\ListResources;
use Laravel\Mcp\Client\Methods\Resources\ReadResource;
use Laravel\Mcp\Client\Methods\Tools\CallTool;
use Laravel\Mcp\Client\Methods\Tools\ListTools;
use Laravel\Mcp\Client\Primitives\Prompt;
use Laravel\Mcp\Client\Primitives\Resource;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Client\Schema\DiscoverResult;
use Laravel\Mcp\Client\Schema\InitializeResult;
use Laravel\Mcp\Client\Schema\PromptResult;
use Laravel\Mcp\Client\Schema\ResourceReadResult;
use Laravel\Mcp\Client\Schema\ToolResult;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Client\Transport\StdioTransport;
use Laravel\Mcp\Client\Transport\TransportFactory;
use Laravel\Mcp\Schema\Implementation;

class Client
{
    protected Protocol $protocol;

    protected ?string $name = null;

    protected ProtocolEra $era = ProtocolEra::AUTO;

    public function __construct(
        protected Transport $transport,
        public ?Implementation $clientInfo = null,
    ) {
        $this->clientInfo = $clientInfo ?? $this->defaultClientInfo();

        $this->protocol = new Protocol($this->transport, $this->clientInfo, $this->era);
    }

    protected function defaultClientInfo(): Implementation
    {
        return new Implementation(
            name: config('app.name', 'Laravel MCP Client'),
            version: '0.0.1',
        );
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
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

    public function discoverResult(): ?DiscoverResult
    {
        return $this->protocol->discoverResult();
    }

    public function withEra(ProtocolEra $era): static
    {
        $this->era = $era;

        $this->protocol->era($era);

        return $this;
    }

    public function era(): ?ProtocolEra
    {
        return $this->protocol->resolvedEra();
    }

    public function ping(): void
    {
        (new Ping)->handle($this->protocol);
    }

    /**
     * @param  iterable<string, Tool>|null  $default
     * @return Collection<string, Tool>
     */
    public function tools(?int $limit = null, ?iterable $default = null): Collection
    {
        try {
            return (new ListTools(client: $this, limit: $limit))->handle($this->protocol);
        } catch (AuthorizationRequiredException $authorizationRequiredException) {
            if ($default === null) {
                throw $authorizationRequiredException;
            }

            return Collection::make($default);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function callTool(string $name, array $arguments = []): ToolResult
    {
        return (new CallTool($name, $arguments))->handle($this->protocol);
    }

    /**
     * @param  iterable<string, Prompt>|null  $default
     * @return Collection<string, Prompt>
     */
    public function prompts(?int $limit = null, ?iterable $default = null): Collection
    {
        try {
            return (new ListPrompts(limit: $limit))->handle($this->protocol);
        } catch (AuthorizationRequiredException $authorizationRequiredException) {
            if ($default === null) {
                throw $authorizationRequiredException;
            }

            return Collection::make($default);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function getPrompt(string $name, array $arguments = []): PromptResult
    {
        return (new GetPrompt($name, $arguments))->handle($this->protocol);
    }

    /**
     * @param  iterable<string, Resource>|null  $default
     * @return Collection<string, Resource>
     */
    public function resources(?int $limit = null, ?iterable $default = null): Collection
    {
        try {
            return (new ListResources(limit: $limit))->handle($this->protocol);
        } catch (AuthorizationRequiredException $authorizationRequiredException) {
            if ($default === null) {
                throw $authorizationRequiredException;
            }

            return Collection::make($default);
        }
    }

    public function readResource(string $uri): ResourceReadResult
    {
        return (new ReadResource($uri))->handle($this->protocol);
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        if ($this->name !== null) {
            return ['name' => $this->name];
        }

        return [
            'name' => null,
            'clientInfo' => $this->clientInfo,
            'transport' => $this->transport->recipe(),
            'era' => $this->era->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        $this->name = $data['name'] ?? null;

        if ($this->name !== null) {
            $resolved = Container::getInstance()->make(ClientManager::class)->build($this->name);

            $this->transport = $resolved->transport;
            $this->clientInfo = $resolved->clientInfo;
            $this->era = $resolved->era;
        } else {
            $this->clientInfo = $data['clientInfo'];
            $this->transport = TransportFactory::fromRecipe($data['transport']);
            $this->era = ProtocolEra::tryFrom($data['era'] ?? '') ?? ProtocolEra::AUTO;
        }

        $this->clientInfo ??= $this->defaultClientInfo();

        $this->protocol = new Protocol($this->transport, $this->clientInfo, $this->era);
    }

    public function __destruct()
    {
        if ($this->connected()) {
            $this->disconnect();
        }
    }
}
