<?php

namespace Laravel\Mcp\Contracts\Methods;

use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Traversable;

interface Method
{
    /** @return JsonRpcResponse|Traversable<JsonRpcResponse> */
    public function handle(JsonRpcMessage $message, SessionContext $context);
}
