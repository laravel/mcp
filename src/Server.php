<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Generator;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Contracts\Transport\Transport;
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
use Laravel\Mcp\Server\Transport\JsonRpcProtocolError;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Throwable;

abstract class Server
{
    /**
     * The versions of the MCP specification supported by the server.
     */
    public array $supportedProtocolVersion = [
        '2025-06-18',
        '2025-03-26',
        '2024-11-05',
    ];

    /**
     * The capabilities of the server.
     */
    public array $capabilities = [
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
     * The name of the MCP server.
     */
    public string $serverName = 'Laravel MCP Server';

    /**
     * The version of the MCP server.
     */
    public string $serverVersion = '0.0.1';

    /**
     * The instructions for the AI.
     */
    public string $instructions = 'This MCP server lets AI agents interact with our Laravel application.';

    /**
     * @var array<string>
     */
    public array $tools = [];

    /**
     * @var array<string>
     */
    public array $resources = [];

    /**
     * @var array<string>
     */
    public array $prompts = [];

    /**
     * The maximum pagination length for tool/list calls.
     */
    public int $maxPaginationLength = 50;

    /**
     * The default pagination length for tool/list calls.
     */
    public int $defaultPaginationLength = 15;

    /**
     * The transport used to communicate with the client.
     */
    protected Transport $transport;

    /**
     * All registered tools once the server is booted (both statically and dynamically added).
     */
    protected array $registeredTools = [];

    protected array $registeredResources = [];

    protected array $registeredPrompts = [];

    /**
     * The JSON-RPC methods available to the server.
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

    /**
     * Create a new MCP server instance.
     */
    public function __construct()
    {
        $this->registeredTools = $this->tools;
        $this->registeredResources = $this->resources;
        $this->registeredPrompts = $this->prompts;
    }

    /**
     * Connect the server to the transport.
     */
    public function connect(Transport $transport)
    {
        $this->transport = $transport;

        $this->boot();

        $this->transport->onReceive(fn ($message) => $this->handle($message));
    }

    /**
     * Process a message from the transport.
     */
    public function handle(string $rawMessage)
    {
        $sessionId = $this->transport->sessionId() ?? Str::uuid()->toString();

        $context = new ServerContext(
            supportedProtocolVersions: $this->supportedProtocolVersion,
            serverCapabilities: $this->capabilities,
            serverName: $this->serverName,
            serverVersion: $this->serverVersion,
            instructions: $this->instructions,
            maxPaginationLength: $this->maxPaginationLength,
            defaultPaginationLength: $this->defaultPaginationLength,
            tools: $this->registeredTools,
            resources: $this->registeredResources,
            prompts: $this->registeredPrompts,
        );

        try {
            $request = JsonRpcRequest::fromJson($rawMessage);

            if ($request->method === 'initialize') {
                return $this->handleInitializeMessage($sessionId, $request, $context);
            }

            if (! isset($request->id)) {
                return; // JSON-RPC notification, no response needed
            }

            if (! isset($this->methods[$request->method])) {
                throw new JsonRpcException("Method not found: {$request->method}", -32601, $request->id);
            }

            $this->handleMessage($sessionId, $request, $context);
        } catch (JsonRpcException $e) {
            $this->transport->send(json_encode($e->toJsonRpcError()));
        } catch (Throwable $e) {
            $jsonRpcError = (new JsonRpcProtocolError(
                code: $e->getCode(),
                message: $e->getMessage(),
                requestId: $request->id ?? null,
                data: null,
            ))->toArray();
            $this->transport->send(json_encode($jsonRpcError));
        }
    }

    /**
     * Boot the server.
     */
    public function boot()
    {
        // Override this method to dynamically add tools, custom methods, etc., when the server boots.
    }

    public function addTool(Tool|string $tool): self
    {
        if (! in_array($tool, $this->registeredTools, true)) {
            $this->registeredTools[] = $tool;
        }

        return $this;
    }

    public function addResource(Resource|string $resource): self
    {
        if (! in_array($resource, $this->registeredResources, true)) {
            $this->registeredResources[] = $resource;
        }

        return $this;
    }

    public function addPrompt(Prompt|string $prompt): self
    {
        if (! in_array($prompt, $this->registeredPrompts, true)) {
            $this->registeredPrompts[] = $prompt;
        }

        return $this;
    }

    /**
     * Add a JSON-RPC method dynamically to the server.
     */
    public function addMethod(string $name, string $handlerClass): self
    {
        $this->methods[$name] = $handlerClass;

        return $this;
    }

    /**
     * Add a capability dynamically to the server.
     */
    public function addCapability(string $key, mixed $value = null): self
    {
        $value = $value ?? (object) [];

        data_set($this->capabilities, $key, $value);

        return $this;
    }

    /**
     * Handle a JSON-RPC message.
     *
     * @throws \Laravel\Mcp\Server\Exceptions\JsonRpcException
     */
    private function handleMessage(string $sessionId, JsonRpcRequest $request, ServerContext $context): void
    {
        /** @var \Laravel\Mcp\Server\Contracts\Methods\Method $methodClass */
        $methodClass = app($this->methods[$request->method]);

        $response = $methodClass->handle($request, $context);

        if ($response instanceof Generator) {
            $this->transport->stream(function () use ($response) {
                foreach ($response as $message) {
                    $this->transport->send($message->toJson());
                }
            });

            return;
        }

        $this->transport->send($response->toJson());
    }

    /**
     * Handle the JSON-RPC initialize message.
     */
    private function handleInitializeMessage(string $sessionId, JsonRpcRequest $request, ServerContext $context)
    {
        $response = (new Initialize)->handle($request, $context);

        $this->transport->send($response->toJson());
    }
}
