<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;

class CallTool implements Method
{
    public function handle(JsonRpcMessage $message, SessionContext $context): JsonRpcResponse
    {
        $tool = new $context->tools[$message->params['name']]();

        return JsonRpcResponse::create(
            $message->id,
            $tool->call($message->params['arguments'])->toArray()
        );
    }
}
