<?php

namespace Laravel\Mcp;

use Laravel\Mcp\Methods\CallTool;
use Laravel\Mcp\Methods\Initialize;
use Laravel\Mcp\Methods\ListTools;
use Laravel\Mcp\Methods\Ping;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Laravel\Mcp\Contracts\Transport\Transport;
use Laravel\Mcp\Exceptions\JsonRpcException;

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

    public string $serverName = 'My Laravel MCP Server';

    public string $serverVersion = '0.1.0';

    public string $instructions = 'Welcome to my Laravel MCP Server!';

    public array $tools = [];

    protected SessionContext $session;

    protected Transport $transport;

    protected array $methods = [
        'tools/list' => ListTools::class,
        'tools/call' => CallTool::class,
        'ping' => Ping::class,
    ];

    public function connect(Transport $transport)
    {
        $this->transport = $transport;

        $this->session = new SessionContext(
            supportedProtocolVersions: $this->supportedProtocolVersion,
            clientCapabilities: [],
            serverCapabilities: $this->capabilities,
            serverName: $this->serverName,
            serverVersion: $this->serverVersion,
            instructions: $this->instructions,
            tools: $this->tools
        );

        $this->boot();

        $this->transport->onReceive(fn ($message) => $this->handle($message));
    }

    public function handle(string $rawMessage)
    {
        try {
            $message = JsonRpcMessage::fromJson($rawMessage);

            if ($message->method === 'initialize') {
                $this->session->clientCapabilities = $message->params['capabilities'] ?? [];

                $response = (new Initialize())->handle($message, $this->session);

                return $this->transport->send($response->toJson());
            }

            if ($message->method === 'notifications/initialized') {
                $this->session->initialized = true;
            }

            if (! isset($message->id) || $message->id === null) {
                return; // This is a generic notification, we'll ignore for now
            }

            if (! $this->session->initialized && $message->method !== 'ping') {
                throw new JsonRpcException("Not initialized.", -32002, $message->id);
            }

            if (! isset($this->methods[$message->method])) {
                throw new JsonRpcException("Method not found: {$message->method}", -32601, $message->id);
            }

            $methodClass = $this->methods[$message->method];

            $methodHandler = new $methodClass();

            $response = $methodHandler->handle($message, $this->session);

            $this->transport->send($response->toJson());
        } catch (JsonRpcException $e) {
            $this->transport->send(json_encode($e->toJsonRpcError()));
        }
    }

    public function boot()
    {
        // Override this method to add custom methods, etc., when the server boots.
    }

    public function addMethod(string $name, string $handlerClass)
    {
        $this->methods[$name] = $handlerClass;
    }
}
