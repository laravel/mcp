<?php

namespace Laravel\Mcp\Contracts\Methods;

use Generator;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

interface Method
{
    /**
     * Implement the JSON-RPC method.
     *
     * @return JsonRpcResponse|Generator<JsonRpcNotification|JsonRpcResponse>
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context);
}
