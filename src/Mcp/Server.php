<?php

namespace Laravel\Mcp\Mcp;

use Laravel\Mcp\Mcp\Transport\Transport;
use Laravel\Mcp\Mcp\Transport\JsonRpcResponse;

abstract class Server
{
    private Transport $transport;

    protected $capabilities = [
        'tools' => [
            'listChanged' => false,
        ],
    ];

    protected $serverName = 'My-MCP-Server';

    protected $serverVersion = '0.1.0';

    protected $instructions = 'Optional instructions for the client';

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

        $response = match ($message['method']) {
            'initialize' => $this->initialize($message['id']),
            default => null,
        };

        $this->transport->send(json_encode($response));
    }

    public function initialize($id)
    {
        return JsonRpcResponse::create($id, [
            'protocolVersion' => '2025-03-26',
            'capabilities' => $this->capabilities,
            'serverInfo' => [
                'name' => $this->serverName,
                'version' => $this->serverVersion,
            ],
            'instructions' => $this->instructions,
        ]);
    }
}
