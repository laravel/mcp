<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Laravel\Mcp\Transport\JsonRpcResponse;

class CustomMethodHandler implements Method
{
    public function handle(JsonRpcMessage $message, ServerContext $context): JsonRpcResponse
    {
        return new JsonRpcResponse(
            id: $message->id,
            result: ['message' => 'Custom method executed successfully!']
        );
    }
}
