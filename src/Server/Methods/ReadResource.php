<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use Laravel\Mcp\Server\Methods\Concerns\ResolvesResources;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

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
        $uri = $request->get('uri');

        try {
            $resource = $this->resolveResource($uri, $context);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new JsonRpcException($invalidArgumentException->getMessage(), -32002, $request->id);
        }

        try {
            $response = $this->invokeResource($resource, $uri);
        } catch (ValidationException $validationException) {
            $response = Response::error('Invalid params: '.ValidationMessages::from($validationException));
        }

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($resource, $uri))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($resource, $uri));
    }

    /**
     * @throws BindingResolutionException
     * @throws ValidationException
     */
    protected function invokeResource(Resource $resource, string $uri): mixed
    {
        $container = Container::getInstance();

        $request = $container->make(Request::class);
        $request->setUri($uri);

        if ($resource instanceof HasUriTemplate) {
            $variables = $resource->uriTemplate()->match($uri) ?? [];
            $request->merge($variables);
        }

        $container->instance(Request::class, $request);

        try {
            // @phpstan-ignore-next-line
            return $container->call([$resource, 'handle']);
        } finally {
            $container->forgetInstance(Request::class);
        }
    }

    protected function serializable(Resource $resource, string $uri): callable
    {
        return fn (ResponseFactory $factory): array => $factory->mergeMeta([
            'contents' => $factory->responses()->map(fn (Response $response): array => [
                ...$response->content()->toResource($resource),
                'uri' => $uri,
            ])->all(),
        ]);
    }
}
