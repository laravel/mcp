<?php

namespace Laravel\Mcp\Contracts\Methods;

use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Generator;

interface Method
{
    /** @return JsonRpcResponse|Generator<JsonRpcResponse> */
    public function handle(JsonRpcRequest $request, ServerContext $context);
}
