<?php

namespace Laravel\Mcp\Contracts;

use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\Message;

interface Method
{
    public function handle(Message $message, ServerContext $context): JsonRpcResponse;
}
