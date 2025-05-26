<?php

namespace Laravel\Mcp;

use Laravel\Mcp\Methods\CallTool;
use Laravel\Mcp\Methods\Initialize;
use Laravel\Mcp\Methods\ListTools;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\Message;
use Laravel\Mcp\Contracts\Transport\Transport;

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

    public function handle(string $message)
    {
        $message = json_decode($message, true);

        if (! isset($message['id']) || $message['id'] === null) {
            return; // Notification
        }

        $methodClass = $this->methods[$message['method']];

        $context = new ServerContext(
            $this->capabilities,
            $this->serverName,
            $this->serverVersion,
            $this->instructions,
            $this->tools
        );

        $methodHandler = new $methodClass();

        $response = $methodHandler->handle(
            new Message($message['id'], $message['params'] ?? []),
            $context
        );

        $this->transport->send($response->toJson());
    }
}
