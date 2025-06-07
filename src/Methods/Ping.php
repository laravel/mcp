<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcRequest;

class Ping implements Method
{
    public function handle(JsonRpcRequest $request, SessionContext $session, ServerContext $context): JsonRpcResponse
    {
        return new JsonRpcResponse(
            id: $request->id,
            result: []
        );
    }
}
