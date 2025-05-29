<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Laravel\Mcp\Transport\JsonRpcResponse;

class CustomMethodHandler implements Method
{
    public function handle(JsonRpcMessage $message, SessionContext $context): JsonRpcResponse
    {
        return new JsonRpcResponse(
            id: $message->id,
            result: ['message' => 'Custom method executed successfully!']
        );
    }
}
