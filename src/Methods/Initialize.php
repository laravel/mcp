<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\Message;

class Initialize
{
    public function handle(Message $message, ServerContext $context): JsonRpcResponse
    {
        return JsonRpcResponse::create($message->id, [
            'protocolVersion' => '2025-03-26',
            'capabilities' => $context->capabilities,
            'serverInfo' => [
                'name' => $context->serverName,
                'version' => $context->serverVersion,
            ],
            'instructions' => $context->instructions,
        ]);
    }
}
