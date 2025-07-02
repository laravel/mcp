<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\Pagination\CursorPaginator;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ListResources implements Method
{
    /**
     * Handle the JSON-RPC resource/list request.
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $encodedCursor = $request->params['cursor'] ?? null;
        $requestedPerPage = $request->params['per_page'] ?? $context->defaultPaginationLength;
        $maxPerPage = $context->maxPaginationLength;

        $perPage = min($requestedPerPage, $maxPerPage);

        $resources = collect($context->resources)->values()
            ->map(fn ($resourceClass) => is_string($resourceClass) ? new $resourceClass : $resourceClass)
            ->map(function ($resource, $index) {
                return [
                    'id' => $index + 1,
                    'uri' => $resource->uri(),
                    'name' => $resource->name(),
                    'title' => $resource->title(),
                    'description' => $resource->description(),
                    'mimeType' => $resource->mimeType(),
                ];
            })
            ->sortBy('id')
            ->values();

        $paginator = new CursorPaginator($resources, $perPage, $encodedCursor);
        $paginationResult = $paginator->paginate();

        $response = [
            'resources' => $paginationResult['items']
                ->map(fn ($resource) => data_forget($resource, 'id'))
                ->toArray(),
        ];

        if (! is_null($paginationResult['nextCursor'])) {
            $response['nextCursor'] = $paginationResult['nextCursor'];
        }

        return JsonRpcResponse::create($request->id, $response);
    }
}
