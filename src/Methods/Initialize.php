<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Messages\InitializeMessage;
use Laravel\Mcp\Server;
use Laravel\Mcp\Transport\JsonRpcResponse;

class Initialize
{
    public function handle(InitializeMessage $message, Server $server): JsonRpcResponse
    {
        return JsonRpcResponse::create($message->id, [
            'protocolVersion' => '2025-03-26',
            'capabilities' => $server::$capabilities,
            'serverInfo' => [
                'name' => $server::$serverName,
                'version' => $server::$serverVersion,
            ],
            'instructions' => $server::$instructions,
        ]);
    }
}
