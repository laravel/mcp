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
        $tools = $this->toolsWithIds($context->tools());
        $perPage = $context->perPage($request->params['per_page'] ?? null);
        $cursor = $request->cursor();

        $paginator = new CursorPaginator($tools, $perPage, $cursor);

        ['items' => $items, 'nextCursor' => $nextCursor] = $paginator->paginate();

        $response = ['tools' => $items];

        if ($nextCursor) {
            $response['nextCursor'] = $nextCursor;
        }

        return JsonRpcResponse::create($request->id, $response);
    }

    public function toolsWithIds(Collection $tools): Collection
    {
        return $tools
            ->map(fn ($tool, $index) => [
                'id' => $index + 1,
                ...$tool->toArray(),
            ])
            ->sortBy('id');
    }
}
