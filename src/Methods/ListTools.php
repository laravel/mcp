<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Contracts\Method;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\Message;

class ListTools implements Method
{
    public function handle(Message $message, ServerContext $context): JsonRpcResponse
    {
        $toolList = collect($context->tools)->values()->map(function (string $toolClass) {
            $tool = new $toolClass();

            return [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(new ToolInputSchema())->toArray(),
            ];
        });

        return JsonRpcResponse::create($message->id, [
            'tools' => $toolList->toArray(),
        ]);
    }
}
