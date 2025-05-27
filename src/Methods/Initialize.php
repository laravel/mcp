<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;

class Initialize implements Method
{
    public function handle(JsonRpcMessage $message, ServerContext $context): JsonRpcResponse
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
