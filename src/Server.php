<?php

namespace Laravel\Mcp;

use Laravel\Mcp\Methods\CallTool;
use Laravel\Mcp\Methods\Initialize;
use Laravel\Mcp\Methods\ListTools;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Laravel\Mcp\Contracts\Transport\Transport;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Throwable;

abstract class Server
{
    public array $capabilities = [
        'tools' => [
            'listChanged' => false,
        ],
    ];

    public string $serverName = 'My Laravel MCP Server';

    public string $serverVersion = '0.1.0';

    public string $instructions = 'Welcome to my Laravel MCP Server!';

    public array $tools = [];

    private Transport $transport;

    private array $methods = [
        'initialize' => Initialize::class,
        'tools/list' => ListTools::class,
        'tools/call' => CallTool::class,
    ];

    public function connect(Transport $transport)
    {
        $this->transport = $transport;
        $this->transport->onReceive(fn ($message) => $this->handle($message));
    }

    public function handle(string $rawMessage)
    {
        try {
            $message = JsonRpcMessage::fromJson($rawMessage);

            if (! isset($message->id) || $message->id === null) {
                return; // This is a Notification request according to the JSON-RPC 2.0 spec
            }

            if (! isset($this->methods[$message->method])) {
                throw new JsonRpcException("Method not found: {$message->method}", -32601, $message->id);
            }

            $methodClass = $this->methods[$message->method];

            $context = new ServerContext(
                $this->capabilities,
                $this->serverName,
                $this->serverVersion,
                $this->instructions,
                $this->tools
            );

            $methodHandler = new $methodClass();

            $response = $methodHandler->handle($message, $context);

            $this->transport->send($response->toJson());
        } catch (JsonRpcException $e) {
            $this->transport->send(json_encode($e->toJsonRpcError()));
        }
    }
}
