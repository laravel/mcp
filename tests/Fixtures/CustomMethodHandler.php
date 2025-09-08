<?php

namespace Tests\Fixtures;

use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResult;

class CustomMethodHandler implements Method
{
    public function __construct(private string $customDependency)
    {
        //
    }

    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResult
    {
        return JsonRpcResult::create($request->id, ['message' => 'Custom method executed successfully!']);
    }
}
