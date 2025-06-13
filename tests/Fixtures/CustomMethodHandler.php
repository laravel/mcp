<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class CustomMethodHandler implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        return JsonRpcResponse::create($request->id, ['message' => 'Custom method executed successfully!']);
    }
}
