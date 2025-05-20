<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\Message;

class CallTool
{
    public function handle(Message $message, ServerContext $context): JsonRpcResponse
    {
        $tool = new $context->tools[$message->params['name']]();

        return JsonRpcResponse::create(
            $message->id,
            $tool->call($message->params['arguments'])->toArray()
        );
    }
}
