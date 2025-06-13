<?php

namespace Laravel\Mcp;

use Laravel\Mcp\Methods\CallTool;
use Laravel\Mcp\Methods\Initialize;
use Laravel\Mcp\Methods\ListTools;
use Laravel\Mcp\Methods\Ping;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Contracts\Transport\Transport;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Illuminate\Support\Str;
use Generator;

abstract class Server
{
    public array $supportedProtocolVersion = [
        '2025-03-26',
    ];

    public array $capabilities = [
        'tools' => [
            'listChanged' => false,
        ],
    ];

    public string $serverName = 'Laravel MCP Server';

    public string $serverVersion = '0.0.1';

    public string $instructions = 'This MCP server lets AI agents interact with our Laravel application.';

    public array $tools = [];

    public int $maxPaginationLength = 50;

    public int $defaultPaginationLength = 15;

    protected Transport $transport;

    protected array $registeredTools = [];

    protected array $methods = [
        'tools/list' => ListTools::class,
        'tools/call' => CallTool::class,
        'ping' => Ping::class,
    ];

    public function __construct()
    {
        $this->registeredTools = $this->tools;
    }

    public function connect(Transport $transport)
    {
        $this->transport = $transport;

        $this->boot();

        $this->transport->onReceive(fn ($message) => $this->handle($message));
    }

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

    public function boot()
    {
        // Override this method to dynamically add tools, custom methods, etc., when the server boots.
    }

    public function addTool($tool)
    {
        if (! in_array($tool, $this->registeredTools)) {
            $this->registeredTools[] = $tool;
        }
    }

    public function addMethod(string $name, string $handlerClass)
    {
        $this->methods[$name] = $handlerClass;
    }

    private function handleMessage(string $sessionId, JsonRpcRequest $request, ServerContext $context)
    {
        $methodClass = $this->methods[$request->method];

        $response = (new $methodClass())->handle($request, $context);

        if ($response instanceof Generator) {
            $this->transport->stream(function() use ($response, $sessionId) {
                foreach ($response as $message) {
                    $this->transport->send($message->toJson(), $sessionId);
                }
            });

            return;
        }

        return $this->transport->send($response->toJson(), $sessionId);
    }

    private function handleInitializeMessage(string $sessionId, JsonRpcRequest $request, ServerContext $context)
    {
        $response = (new Initialize())->handle($request, $context);

        $this->transport->send($response->toJson(), $sessionId);
    }
}
