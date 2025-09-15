<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Pagination\CursorPaginator;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class ListTools implements Method
{
    public function handle(JsonRpcRequest $jsonRpcRequest, ServerContext $context): JsonRpcResponse
    {
        $request = $jsonRpcRequest->toRequest();

        $paginator = new CursorPaginator(
            items: $context->tools($request),
            perPage: $context->perPage($jsonRpcRequest->get('per_page')),
            cursor: $jsonRpcRequest->cursor(),
        );

        return JsonRpcResponse::result($jsonRpcRequest->id, $paginator->paginate('tools'));
    }
}
