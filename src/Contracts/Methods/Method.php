<?php

namespace Laravel\Mcp\Contracts\Methods;

use Laravel\Mcp\ServerContext;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Generator;

interface Method
{
    /** @return JsonRpcResponse|Generator<JsonRpcResponse> */
    public function handle(JsonRpcMessage $message, SessionContext $session, ServerContext $context);
}
