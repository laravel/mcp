<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Pagination\CursorPaginator;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ListSkills implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $cursor = $request->get('cursor');
        $perPage = $request->get('per_page');

        if ($cursor !== null && ! is_string($cursor)) {
            throw new JsonRpcException('The [cursor] parameter must be a string.', ErrorCode::INVALID_PARAMS->value, $request->id);
        }

        if ($perPage !== null && (! is_int($perPage) || $perPage < 1)) {
            throw new JsonRpcException('The [per_page] parameter must be a positive integer.', ErrorCode::INVALID_PARAMS->value, $request->id);
        }

        $paginator = new CursorPaginator(
            items: $context->skills(),
            perPage: $context->perPage($perPage),
            cursor: $cursor,
        );

        return JsonRpcResponse::result($request->id, $paginator->paginate('skills'));
    }
}
