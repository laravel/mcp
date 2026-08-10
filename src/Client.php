<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Mcp\Client\ClientManager;
use Laravel\Mcp\Client\Contracts\Transport;
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
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Schema\Implementation;

class Client
{
    protected Protocol $protocol;

    protected ?string $name = null;

    /** @var array<string, array<string, mixed>> */
    protected array $toolSchemas = [];

    public function __construct(
        protected Transport $transport,
        public ?Implementation $clientInfo = null,
    ) {
        $this->clientInfo = $clientInfo ?? $this->defaultClientInfo();

        $this->protocol = new Protocol($this->transport, $this->clientInfo);
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

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        $this->protocol->connect();

        return $this->protocol->capabilities();
    }

    public function serverInfo(): ?Implementation
    {
        $this->protocol->connect();

        return $this->protocol->serverInfo();
    }

    public function instructions(): ?string
    {
        $this->protocol->connect();

        return $this->protocol->instructions();
    }

    public function withProtocolVersion(?ProtocolVersion $version): static
    {
        if ($version instanceof ProtocolVersion && ! in_array($version->value, ProtocolVersion::clientSupported(), true)) {
            throw new ClientException(sprintf(
                'This client does not support protocol version [%s]. It supports [%s].',
                $version->value,
                implode(', ', ProtocolVersion::clientSupported()),
            ));
        }

        $this->protocol->pinProtocolVersion($version);

        return $this;
    }

    public function protocolVersion(): ProtocolVersion
    {
        $this->protocol->connect();

        return $this->protocol->connectionProtocol();
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
            $tools = (new ListTools(client: $this, limit: $limit))->handle($this->protocol);

            foreach ($tools as $tool) {
                $this->toolSchemas[$tool->name] = $tool->inputSchema;
            }

            return $tools;
        } catch (AuthorizationRequiredException $authorizationRequiredException) {
            if ($default === null) {
                throw $authorizationRequiredException;
            }

            return Collection::make($default);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $inputSchema
     */
    public function callTool(string $name, array $arguments = [], ?array $inputSchema = null): ToolResult
    {
        return (new CallTool(
            $name,
            $arguments,
            $inputSchema ?? $this->toolSchemas[$name] ?? [],
        ))->handle($this->protocol);
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
            'protocolVersion' => $this->protocol->pinnedProtocolVersion()?->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        $this->name = Arr::get($data, 'name');

        if ($this->name !== null) {
            $resolved = Container::getInstance()->make(ClientManager::class)->build($this->name);

            $this->transport = $resolved->transport;
            $this->clientInfo = $resolved->clientInfo;
            $pinned = $resolved->protocol->pinnedProtocolVersion();
        } else {
            $this->clientInfo = Arr::get($data, 'clientInfo');
            $this->transport = TransportFactory::fromRecipe(Arr::get($data, 'transport'));
            $pinned = ProtocolVersion::tryFrom((string) Arr::get($data, 'protocolVersion'));
        }

        $this->clientInfo ??= $this->defaultClientInfo();

        $this->protocol = new Protocol($this->transport, $this->clientInfo, $pinned);
    }

    public function __destruct()
    {
        if ($this->connected()) {
            $this->disconnect();
        }
    }
}
