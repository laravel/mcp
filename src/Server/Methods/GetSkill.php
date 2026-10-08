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
        $uri = $request->get('uri');

        if (! is_string($uri) || $uri === '') {
            throw new JsonRpcException('The [uri] parameter must be a non-empty string.', ErrorCode::INVALID_PARAMS->value, $request->id);
        }

        $skill = $context->skills()->first(fn (Skill $skill): bool => $skill->uri() === $uri);

        if ($skill === null) {
            throw new JsonRpcException("Skill [{$uri}] not found.", ErrorCode::INVALID_PARAMS->value, $request->id);
        }

        $context->resources();

        return JsonRpcResponse::result($request->id, ['skill' => $skill->toArray()]);
    }
}
