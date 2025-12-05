<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Contracts\SupportsCompletion;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\Concerns\ResolvesPrompts;
use Laravel\Mcp\Server\Methods\Concerns\ResolvesResources;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class CompletionComplete implements Method
{
    use ResolvesPrompts;
    use ResolvesResources;

    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        if (! isset($context->serverCapabilities['completions'])) {
            throw new JsonRpcException(
                'Server does not support completions capability.',
                -32601,
                $request->id,
            );
        }

        $ref = $request->get('ref');
        $argument = $request->get('argument');

        if (is_null($ref) || is_null($argument)) {
            throw new JsonRpcException(
                'Missing required parameters: ref and argument',
                -32602,
                $request->id,
            );
        }

        try {
            $primitive = $this->resolvePrimitive($ref, $context);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new JsonRpcException($invalidArgumentException->getMessage(), -32602, $request->id);
        }

        if (! $primitive instanceof SupportsCompletion) {
            throw new JsonRpcException(
                'The referenced primitive does not support completion.',
                -32602,
                $request->id,
            );
        }

        $argumentName = $argument['name'] ?? null;
        $argumentValue = $argument['value'] ?? '';

        if (is_null($argumentName)) {
            throw new JsonRpcException(
                'Missing argument name.',
                -32602,
                $request->id,
            );
        }

        $result = $this->invokeCompletion($primitive, $argumentName, $argumentValue);

        return JsonRpcResponse::result($request->id, [
            'completion' => $result->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $ref
     */
    protected function resolvePrimitive(array $ref, ServerContext $context): Primitive|Resource|HasUriTemplate
    {
        return match (Arr::get($ref, 'type')) {
            'ref/prompt' => $this->resolvePrompt(Arr::get($ref, 'name'), $context),
            'ref/resource' => $this->resolveResource(Arr::get($ref, 'uri'), $context),
            default => throw new InvalidArgumentException('Invalid reference type. Expected ref/prompt or ref/resource.'),
        };
    }

    protected function invokeCompletion(
        SupportsCompletion $primitive,
        string $argumentName,
        string $argumentValue
    ): mixed {
        $container = Container::getInstance();

        $result = $container->call($primitive->complete(...), [
            'argument' => $argumentName,
            'value' => $argumentValue,
        ]);

        return $result->resolve($argumentValue);
    }
}
