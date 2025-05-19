<?php

namespace Laravel\Mcp;

use Laravel\Mcp\Methods\CallTool;
use Laravel\Mcp\Methods\Initialize;
use Laravel\Mcp\Methods\ListTools;
use Laravel\Mcp\Messages\CallToolMessage;
use Laravel\Mcp\Messages\InitializeMessage;
use Laravel\Mcp\Messages\ListToolsMessage;
use Laravel\Mcp\Transport\Transport;

abstract class Server
{
    public static array $capabilities = [
        'tools' => [
            'listChanged' => false,
        ],
    ];

    public static string $serverName = 'My Laravel MCP Server';

    public static string $serverVersion = '0.1.0';

    public static string $instructions = 'Welcome to my Laravel MCP Server!';

    public static array $tools = [];

    private Transport $transport;

    private static array $methods = [
        'initialize' => [Initialize::class, InitializeMessage::class],
        'tools/list' => [ListTools::class, ListToolsMessage::class],
        'tools/call' => [CallTool::class, CallToolMessage::class],
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

        list($methodClass, $messageClass) = self::$methods[$message['method']];

        $response = (new $methodClass())->handle(new $messageClass($message), $this);

        $this->transport->send($response->toJson());
    }
}
