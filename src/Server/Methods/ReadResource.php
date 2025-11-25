<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ResourceTemplate;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

class ReadResource implements Method
{
    use InteractsWithResponses;

    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $uri = $request->get('uri') ?? throw new JsonRpcException(
            'Missing [uri] parameter.',
            -32002,
            $request->id,
        );

        $resource = $context->resources()->first(fn (Resource $resource): bool => $resource->uri() === $uri)
            ?? $context->resourceTemplates()->first(fn (ResourceTemplate $template): bool => ! is_null($template->uriTemplate()->match($uri)));

        if (! $resource) {
            throw new JsonRpcException("Resource [{$uri}] not found.", -32002, $request->id);
        }

        try {
            $response = $this->invokeResource($resource, $uri);
        } catch (ValidationException $validationException) {
            $response = Response::error('Invalid params: '.ValidationMessages::from($validationException));
        }

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($resource))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($resource));
    }

    protected function invokeResource(Resource $resource, string $uri): mixed
    {
        $container = Container::getInstance();

        if ($resource instanceof ResourceTemplate) {
            $variables = $resource->uriTemplate()->match($uri) ?? [];
            $resource->setUri($uri);

            if ($container->bound('mcp.request')) {
                /** @var Request $request */
                $request = $container->make('mcp.request');
                $request->setArguments([
                    ...$request->all(),
                    ...$variables,
                ]);
            } else {
                $request = new Request([...$variables]);
            }

            // @phpstan-ignore-next-line
            return $container->call([$resource, 'handle'], ['request' => $request]);
        }

        // @phpstan-ignore-next-line
        return Container::getInstance()->call([$resource, 'handle']);
    }

    protected function serializable(Resource $resource): callable
    {
        return fn (ResponseFactory $factory): array => $factory->mergeMeta([
            'contents' => $factory->responses()->map(fn (Response $response): array => $response->content()->toResource($resource))->all(),
        ]);
    }
}
