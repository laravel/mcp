<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Pagination\CursorPaginator;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class ListResources implements Method
{
    public function handle(JsonRpcRequest $jsonRpcRequest, ServerContext $context): JsonRpcResponse
    {
        $paginator = new CursorPaginator(
            items: $context->resources($jsonRpcRequest->toRequest()),
            perPage: $context->perPage($jsonRpcRequest->get('per_page')),
            cursor: $jsonRpcRequest->cursor(),
        );

        return JsonRpcResponse::result($jsonRpcRequest->id, $paginator->paginate('resources'));
    }
}
