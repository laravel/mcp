<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Illuminate\Support\ItemNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Server\Contracts\Methods\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class GetPrompt implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        if (is_null($request->get('name'))) {
            throw new InvalidArgumentException('Missing required parameter: name');
        }

        $prompt = $context->prompts()->first(fn ($prompt) => $prompt->name() === $request->get('name'));
        if (is_null($prompt)) {
            throw new ItemNotFoundException('Prompt not found');
        }

        return new JsonRpcResponse(
            $request->id,
            $prompt->handle($request->get('arguments', []))->toArray(),
        );
    }
}
