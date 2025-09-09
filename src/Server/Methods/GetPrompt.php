<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Illuminate\Container\Container;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

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

        try {
            $result = Container::getInstance()->call(
                [$prompt, 'handle'],
                ['request' => new Request(
                    $request->get('arguments', []),
                )],
            );
        } catch (ValidationException $validationException) {
            return JsonRpcResponse::error(
                id: $request->id,
                code: -32602,
                message: 'Invalid params: '.ValidationMessages::from($validationException),
            );
        }

        return JsonRpcResponse::result($request->id, $result);
    }
}
