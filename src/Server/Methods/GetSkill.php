<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Skill;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class GetSkill implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $request->validate([
            'uri' => ['nullable', 'string'],
        ]);

        $uri = $request->get('uri');

        $skill = $context->skills()->first(fn (Skill $skill): bool => $skill->uri() === $uri);

        if (! $skill instanceof Skill) {
            throw new JsonRpcException('The [uri] parameter must be the URI of a registered skill.', ErrorCode::INVALID_PARAMS->value, $request->id);
        }

        return JsonRpcResponse::result($request->id, ['skill' => $skill->toArray()]);
    }
}
