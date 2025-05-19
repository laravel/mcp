<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Messages\CallToolMessage;
use Laravel\Mcp\Server;
use Laravel\Mcp\Transport\JsonRpcResponse;

class CallTool
{
    public function handle(CallToolMessage $message, Server $server): JsonRpcResponse
    {
        $tool = new $server::$tools[$message->toolName]();

        return JsonRpcResponse::create(
            $message->id,
            $tool->call($message->toolArguments)
                ->toArray()
        );
    }
}
