<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class CustomMethodHandler implements Method
{
    public function handle(JsonRpcRequest $request, SessionContext $session, ServerContext $context): JsonRpcResponse
    {
        return new JsonRpcResponse(
            id: $request->id,
            result: ['message' => 'Custom method executed successfully!']
        );
    }
}
