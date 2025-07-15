<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server\Contracts\Methods\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class CustomMethodHandler implements Method
{
    public function __construct(private string $customDependency) {}

    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        return JsonRpcResponse::create($request->id, ['message' => 'Custom method executed successfully!']);
    }
}
