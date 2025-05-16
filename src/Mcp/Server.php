<?php

namespace Laravel\Mcp\Mcp;

use Laravel\Mcp\Mcp\Transport\Transport;

abstract class Server
{
    private Transport $transport;

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
            'initialize' => $this->initialize($message),
            default => null,
        };

        $this->transport->send(json_encode($response));
    }

    public function initialize(array $message)
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $message['id'],
            'result' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [
                    'tools' => [
                        'listChanged' => false,
                    ],
                ],
                'serverInfo' => [
                    'name' => 'My-MCP-Server',
                    'version' => '0.1.0',
                ],
                'instructions' => 'Optional instructions for the client',
            ],
        ];
    }
}
