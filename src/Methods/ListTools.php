<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\Pagination\CursorPaginator;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ListTools implements Method
{
    /**
     * Handle the JSON-RPC tool/list request.
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $encodedCursor = $request->params['cursor'] ?? null;
        $requestedPerPage = $request->params['per_page'] ?? $context->defaultPaginationLength;
        $maxPerPage = $context->maxPaginationLength;

        $perPage = min($requestedPerPage, $maxPerPage);

        $tools = collect($context->tools)->values()
            ->map(fn ($toolClass) => is_string($toolClass) ? new $toolClass : $toolClass)
            ->map(function ($tool, $index) {
                return [
                    'id' => $index + 1,
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'inputSchema' => $tool->schema(new ToolInputSchema)->toArray(),
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

        return JsonRpcResponse::create($request->id, $response);
    }
}
