<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\Pagination\CursorPaginator;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;

class ListTools implements Method
{
    public function handle(JsonRpcMessage $message, SessionContext $context): JsonRpcResponse
    {
        $encodedCursor = $message->params['cursor'] ?? null;
        $perPage = 10; // TODO: Should be configurable

        $tools = collect($context->tools)->values()
            ->map(fn($toolClass) => new $toolClass())
            ->map(function ($tool, $index) {
                return [
                    'id' => $index + 1,
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'inputSchema' => $tool->getInputSchema(new ToolInputSchema())->toArray(),
                ];
            })
            ->sortBy('id')
            ->values();

        $paginator = new CursorPaginator($tools, $perPage, $encodedCursor);
        $paginationResult = $paginator->paginate();

        $response = [
            'tools' => $paginationResult['items']->toArray(),
        ];

        if (! is_null($paginationResult['nextCursor'])) {
            $response['nextCursor'] = $paginationResult['nextCursor'];
        }

        return JsonRpcResponse::create($message->id, $response);
    }
}
