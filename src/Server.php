<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\Methods\GetPrompt;
use Laravel\Mcp\Server\Methods\Initialize;
use Laravel\Mcp\Server\Methods\ListPrompts;
use Laravel\Mcp\Server\Methods\ListResources;
use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\Methods\Ping;
use Laravel\Mcp\Server\Methods\ReadResource;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Throwable;

abstract class Server
{
    protected string $name = 'Laravel MCP Server';

    protected string $version = '0.0.1';

    protected string $instructions = 'This MCP server lets AI agents interact with our Laravel application.';

    /**
     * @var array<int, string>
     */
    protected array $supportedProtocolVersion = [
        '2025-06-18',
        '2025-03-26',
        '2024-11-05',
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $capabilities = [
        'tools' => [
            'listChanged' => false,
        ],
        'resources' => [
            'listChanged' => false,
        ],
        'prompts' => [
            'listChanged' => false,
        ],
    ];

    /**
     * @var array<int, Tool|class-string<Tool>>
     */
    protected array $tools = [];

    /**
     * @var array<int, Resource|class-string<Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, Prompt|class-string<Prompt>>
     */
    protected array $prompts = [];

    public int $maxPaginationLength = 50;

    public int $defaultPaginationLength = 15;

    protected Transport $transport;

    /**
     * @var array<string, class-string<Method>>
     */
    protected array $methods = [
        'tools/list' => ListTools::class,
        'tools/call' => CallTool::class,
        'resources/list' => ListResources::class,
        'resources/read' => ReadResource::class,
        'prompts/list' => ListPrompts::class,
        'prompts/get' => GetPrompt::class,
        'ping' => Ping::class,
    ];

    public function connect(Transport $transport): void
    {
        $this->transport = $transport;

        $this->boot();

        $this->transport->onReceive(fn (string $message) => $this->handle($message));
    }

    public function handle(string $rawMessage): void
    {
        $sessionId = $this->transport->sessionId() ?? Str::uuid()->toString();

        $context = new ServerContext(
            supportedProtocolVersions: $this->supportedProtocolVersion,
            serverCapabilities: $this->capabilities,
            serverName: $this->name,
            serverVersion: $this->version,
            instructions: $this->instructions,
            maxPaginationLength: $this->maxPaginationLength,
            defaultPaginationLength: $this->defaultPaginationLength,
            tools: $this->tools,
            resources: $this->resources,
            prompts: $this->prompts,
        );

        try {
            $request = JsonRpcRequest::fromJson($rawMessage);

            if ($request->method === 'initialize') {
                $this->handleInitializeMessage($sessionId, $request, $context);

                return;
            }

            if (! isset($request->id)) {
                return; // JSON-RPC notification, no response needed
            }

            if (! isset($this->methods[$request->method])) {
                throw new JsonRpcException("Method not found: {$request->method}", -32601, $request->id);
            }

            $this->handleMessage($sessionId, $request, $context);
        } catch (JsonRpcException $e) {
            $this->transport->send($e->toJsonRpcResponse()->toJson());
        } catch (Throwable $e) {
            $jsonRpcResponse = JsonRpcResponse::error(
                $request->id ?? null,
                $e->getCode(),
                $e->getMessage(),
            );

            $this->transport->send($jsonRpcResponse->toJson());
        }
    }

    public function boot(): void
    {
        // Override this method to dynamically add tools, custom methods, etc., when the server boots.
    }

    /**
     * @param  Tool|class-string<Tool>  $tool
     */
    public function addTool(Tool|string $tool): static
    {
        if (! in_array($tool, $this->tools, true)) {
            $this->tools[] = $tool;
        }

        return $this;
    }

    /**
     * @param  Resource|class-string<Resource>  $resource
     */
    public function addResource(Resource|string $resource): static
    {
        if (! in_array($resource, $this->resources, true)) {
            $this->resources[] = $resource;
        }

        return $this;
    }

    /**
     * @param  Prompt|class-string<Prompt>  $prompt
     */
    public function addPrompt(Prompt|string $prompt): static
    {
        if (! in_array($prompt, $this->prompts, true)) {
            $this->prompts[] = $prompt;
        }

        return $this;
    }

    /**
     * @param  class-string<Method>  $handlerClass
     */
    public function addMethod(string $name, string $handlerClass): static
    {
        $this->methods[$name] = $handlerClass;

        return $this;
    }

    public function addCapability(string $key, mixed $value = null): static
    {
        $value ??= (object) [];

        data_set($this->capabilities, $key, $value);

        return $this;
    }

    /**
     * @throws JsonRpcException
     */
    protected function handleMessage(string $sessionId, JsonRpcRequest $request, ServerContext $context): void
    {
        /** @var Method $methodClass */
        $methodClass = Container::getInstance()->make(
            $this->methods[$request->method],
        );

        $response = $methodClass->handle($request, $context);

        if (! is_iterable($response)) {
            $this->transport->send($response->toJson());

            return;
        }

        $this->transport->stream(function () use ($response): void {
            foreach ($response as $message) {
                $this->transport->send($message->toJson());
            }
        });
    }

    protected function handleInitializeMessage(string $sessionId, JsonRpcRequest $request, ServerContext $context): void
    {
        $response = (new Initialize)->handle($request, $context);

        $this->transport->send($response->toJson());
    }
}
