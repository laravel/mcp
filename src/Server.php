<?php

namespace Laravel\Mcp;

use Laravel\Mcp\Contracts\Tool;
use Laravel\Mcp\Transport\Transport;
use Laravel\Mcp\Transport\JsonRpcResponse;

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

    protected $tools = [];

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
            'tools/list' => $this->listTools($message['id']),
            'tools/call' => $this->callTool($message['id'], $message['params']),
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

    public function listTools($id)
    {
        $tools = collect($this->tools)->values()->map(function (string $toolClass) {
            $tool = new $toolClass();

            return [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
            ];
        });

        return JsonRpcResponse::create($id, [
            'tools' => $tools,
        ]);
    }

    public function callTool($id, $parameters)
    {
        $tool = new $this->tools[$parameters['name']]();

        return JsonRpcResponse::create($id, $tool->call($parameters['arguments']));
    }
}
