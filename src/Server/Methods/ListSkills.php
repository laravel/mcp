<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Pagination\CursorPaginator;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Skill;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ListSkills implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $skills = $context->skills();

        $result = (new CursorPaginator(
            items: $skills->keys(),
            perPage: $context->perPage($request->get('per_page')),
            cursor: $request->cursor(),
        ))->paginate('skills');

        $result['skills'] = $skills->only($result['skills'])
            ->map(fn (Skill $skill): ?array => $context->skillCatalog()->manifest($skill))
            ->filter()
            ->values()
            ->all();

        return JsonRpcResponse::result($request->id, $result);
    }
}
