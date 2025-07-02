<?php

namespace Laravel\Mcp\Methods;

use Illuminate\Support\Collection;
use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\Pagination\CursorPaginator;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ListTools implements Method
{
    /**
     * Handle the JSON-RPC tool/list request.
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $perPage = $context->perPage($request->params['per_page'] ?? null);
        $cursor = $request->cursor();

        $paginator = new CursorPaginator($context->tools(), $perPage, $cursor);

        ['items' => $items, 'nextCursor' => $nextCursor] = $paginator->paginate();

        $response = ['tools' => $items];

        if ($nextCursor) {
            $response['nextCursor'] = $nextCursor;
        }

        return JsonRpcResponse::create($request->id, $response);
    }
}
