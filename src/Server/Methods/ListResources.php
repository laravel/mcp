<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Pagination\CursorPaginator;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Skill;
use Laravel\Mcp\Server\Skills\SkillResource;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ListResources implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $request->validate([
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer:strict', 'min:1'],
        ]);

        $resources = $context->resources()->concat(
            $context->skills()->map(fn (Skill $skill): SkillResource => $skill->resource('SKILL.md')),
        );

        $paginator = new CursorPaginator(
            items: $resources,
            perPage: $context->perPage($request->get('per_page')),
            cursor: $request->cursor(),
        );

        return JsonRpcResponse::result($request->id, $paginator->paginate('resources'));
    }
}
