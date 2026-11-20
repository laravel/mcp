<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Contracts\Container\BindingResolutionException;
use InvalidArgumentException;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use Laravel\Mcp\Server\Methods\Concerns\ResolvesResources;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ResourceInvoker;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ReadResource implements Method
{
    use InteractsWithResponses;
    use ResolvesResources;

    /**
     * @return Generator<JsonRpcResponse>|JsonRpcResponse
     *
     * @throws BindingResolutionException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $request->validate([
            'uri' => ['nullable', 'string'],
        ]);

        $uri = $request->get('uri');

        try {
            $resource = $this->resolveResource($uri, $context);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new JsonRpcException(
                $invalidArgumentException->getMessage(),
                ErrorCode::INVALID_PARAMS->value,
                $request->id,
                is_string($uri) && $uri !== '' ? ['uri' => $uri] : null,
            );
        }

        $response = $this->callHandler(fn (): mixed => (new ResourceInvoker)->invoke($resource, $uri), $request);

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($resource, $uri))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($resource, $uri));
    }

    protected function serializable(Resource $resource, string $uri): callable
    {
        return fn (ResponseFactory $factory): array => $factory->mergeMeta([
            'contents' => $factory->responses()
                ->map(fn (Response $response): array => (new ResourceInvoker)->contents($resource, $uri, $response))
                ->all(),
        ]);
    }
}
