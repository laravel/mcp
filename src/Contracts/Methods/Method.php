<?php

namespace Laravel\Mcp\Contracts\Methods;

use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;

interface Method
{
    public function handle(JsonRpcMessage $message, SessionContext $context): JsonRpcResponse;
}
