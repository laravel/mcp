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
use Laravel\Mcp\Server\Support\UriTemplateMatcher;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

class ReadResource implements Method
{
    use InteractsWithResponses;

    /**
     * @return Generator<JsonRpcResponse>|JsonRpcResponse
     *
     * @throws JsonRpcException
     */
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

        $resource = $context->resources()
            ->first(fn (Resource $resource): bool => $resource->uri() === $uri);

        if (! $resource) {
            $resource = $context->resourceTemplates()
                ->first(fn (ResourceTemplate $template): bool => UriTemplateMatcher::matches($template->uriTemplate(), $uri));

            if ($resource instanceof ResourceTemplate) {
                $variables = UriTemplateMatcher::extract($resource->uriTemplate(), $uri);

                Container::getInstance()->afterResolving(Request::class, function (Request $mcpRequest) use ($variables): void {
                    foreach ($variables as $key => $value) {
                        $mcpRequest->merge([$key => $value]);
                    }
                });
            }
        }

        if (! $resource) {
            throw new JsonRpcException(
                "Resource [{$uri}] not found.",
                -32002,
                $request->id,
            );
        }

        try {
            // @phpstan-ignore-next-line
            $response = Container::getInstance()->call([$resource, 'handle']);
        } catch (ValidationException $validationException) {
            $response = Response::error('Invalid params: '.ValidationMessages::from($validationException));
        }

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($resource, $uri))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($resource, $uri));
    }

    protected function serializable(Resource|ResourceTemplate $resource, string $requestedUri): callable
    {
        return fn (Collection $responses): array => [
            'contents' => $responses->map(function (Response $response) use ($resource, $requestedUri): array {
                $content = $response->content()->toResource($resource);

                $content['uri'] = $requestedUri;

                return $content;
            })->all(),
        ];
    }
}
