<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
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
        if (is_null($request->get('uri'))) {
            throw new JsonRpcException(
                'Missing [uri] parameter.',
                -32002,
                $request->id,
            );
        }

        $uri = $request->get('uri');

        $resource = $this->findResource($context->resources(), $uri);

        if (! $resource instanceof Resource) {
            throw new JsonRpcException(
                "Resource [{$uri}] not found.",
                -32002,
                $request->id,
            );
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

    /**
     * @param  Collection<int, Resource>  $resources
     */
    protected function findResource(Collection $resources, string $uri): ?Resource
    {
        $resource = $resources->first(
            fn (Resource $r): bool => ! ($r instanceof ResourceTemplate) && $r->uri() === $uri
        );

        if ($resource) {
            return $resource;
        }

        return $resources
            ->filter(fn (Resource $r): bool => $r instanceof ResourceTemplate)
            // @phpstan-ignore-next-line
            ->first(fn (ResourceTemplate $template): bool => $template->uriTemplate()->match($uri) !== null);
    }

    protected function invokeResource(Resource $resource, string $uri): mixed
    {
        if ($resource instanceof ResourceTemplate) {
            $variables = $resource->uriTemplate()->match($uri) ?? [];
            $templateRequest = new Request(['uri' => $uri, ...$variables]);

            return Container::getInstance()->call($resource->handle(...), ['request' => $templateRequest]);
        }

        // @phpstan-ignore-next-line
        return Container::getInstance()->call([$resource, 'handle']);
    }

    protected function serializable(Resource $resource): callable
    {
        return fn (Collection $responses): array => [
            'contents' => $responses->map(fn (Response $response): array => $response->content()->toResource($resource))->all(),
        ];
    }
}
