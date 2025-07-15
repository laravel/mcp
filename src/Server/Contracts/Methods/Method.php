<?php

namespace Laravel\Mcp\Server\Contracts\Methods;

use Generator;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Server\Transport\JsonRpcNotification;

interface Method
{
    /**
     * Implement the JSON-RPC method.
     *
     * @return JsonRpcResponse|Generator<JsonRpcNotification|JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context);
}
