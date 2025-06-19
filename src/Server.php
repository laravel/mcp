<?php

namespace Laravel\Mcp;

use Generator;
use Illuminate\Support\Str;
use Laravel\Mcp\Contracts\Transport\Transport;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Methods\CallTool;
use Laravel\Mcp\Methods\Initialize;
use Laravel\Mcp\Methods\ListTools;
use Laravel\Mcp\Methods\Ping;
use Laravel\Mcp\Transport\JsonRpcRequest;

abstract class Server
{
    /**
     * The versions of the MCP specification supported by the server.
     */
    public array $supportedProtocolVersion = [
        '2025-03-26',
    ];

    /**
     * The capabilities of the server.
     */
    public array $capabilities = [
        'tools' => [
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
     * The available tools.
     */
    public array $tools = [];

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

    /**
     * The registered method resolvers.
     *
     * @var array<string, callable>
     */
    protected array $methodResolvers = [];

    /**
     * The JSON-RPC methods available to the server.
     */
    protected array $methods = [
        'tools/list' => ListTools::class,
        'tools/call' => CallTool::class,
        'ping' => Ping::class,
    ];

    /**
     * Create a new MCP server instance.
     */
    public function __construct()
    {
        $this->registeredTools = $this->tools;
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
            tools: $this->registeredTools,
            maxPaginationLength: $this->maxPaginationLength,
            defaultPaginationLength: $this->defaultPaginationLength,
        );

        try {
            $request = JsonRpcRequest::fromJson($rawMessage);

            if ($request->method === 'initialize') {
                return $this->handleInitializeMessage($sessionId, $request, $context);
            }

            if (! isset($request->id) || $request->id === null) {
                return; // JSON-RPC notification, no response needed
            }

            if (! isset($this->methods[$request->method])) {
                throw new JsonRpcException("Method not found: {$request->method}", -32601, $request->id);
            }

            $this->handleMessage($sessionId, $request, $context);
        } catch (JsonRpcException $e) {
            $this->transport->send(json_encode($e->toJsonRpcError()), $sessionId);
        }
    }

    /**
     * Boot the server.
     */
    public function boot()
    {
        // Override this method to dynamically add tools, custom methods, etc., when the server boots.
    }

    /**
     * Add a tool dynamically to the server.
     */
    public function addTool($tool)
    {
        if (! in_array($tool, $this->registeredTools)) {
            $this->registeredTools[] = $tool;
        }
    }

    /**
     * Add a JSON-RPC method dynamically to the server.
     */
    public function addMethod(string $name, string $handlerClass)
    {
        $this->methods[$name] = $handlerClass;
    }

    /**
     * Register a custom method resolver.
     */
    public function resolveMethodUsing(string $class, callable $resolver): void
    {
        $this->methodResolvers[$class] = $resolver;
    }

    /**
     * Add a capability dynamically to the server.
     */
    public function addCapability(string $key, mixed $value)
    {
        data_set($this->capabilities, $key, $value);
    }

    /**
     * Resolve a method handler.
     */
    protected function resolveMethod(string $class): object
    {
        if (isset($this->methodResolvers[$class])) {
            return ($this->methodResolvers[$class])();
        }

        return new $class;
    }

    /**
     * Handle a JSON-RPC message.
     */
    private function handleMessage(string $sessionId, JsonRpcRequest $request, ServerContext $context)
    {
        $methodClass = $this->methods[$request->method];

        $response = $this->resolveMethod($methodClass)->handle($request, $context);

        if ($response instanceof Generator) {
            $this->transport->stream(function () use ($response, $sessionId) {
                foreach ($response as $message) {
                    $this->transport->send($message->toJson(), $sessionId);
                }
            });

            return;
        }

        return $this->transport->send($response->toJson(), $sessionId);
    }

    /**
     * Handle the JSON-RPC initialize message.
     */
    private function handleInitializeMessage(string $sessionId, JsonRpcRequest $request, ServerContext $context)
    {
        $response = (new Initialize)->handle($request, $context);

        $this->transport->send($response->toJson(), $sessionId);
    }
}
