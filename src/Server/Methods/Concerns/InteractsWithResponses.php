<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods\Concerns;

use Generator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

trait InteractsWithResponses
{
    /**
     * @param  array<int, Response|ResponseFactory|string>|Response|ResponseFactory|string  $response
     */
    protected function toJsonRpcResponse(JsonRpcRequest $request, Response|ResponseFactory|array|string $response, callable $serializable): JsonRpcResponse
    {
        $responseFactory = $this->toResponseFactory($response);

        $responseFactory->responses()->each(function (Response $response) use ($request): void {
            if (! $this instanceof Errable && $response->isError()) {
                throw new JsonRpcException(
                    $response->content()->__toString(), // @phpstan-ignore-line
                    -32603,
                    $request->id,
                );
            }
        });

        return JsonRpcResponse::result($request->id, $serializable($responseFactory));
    }

    /**
     * @param  iterable<Response|ResponseFactory|string>  $responses
     * @return Generator<JsonRpcResponse>
     */
    protected function toJsonRpcStreamedResponse(JsonRpcRequest $request, iterable $responses, callable $serializable): Generator
    {
        /** @var array<int, Response|ResponseFactory|string> $pendingResponses */
        $pendingResponses = [];

        try {
            foreach ($responses as $response) {
                if ($response instanceof Response && $response->isNotification()) {
                    /** @var Notification $content */
                    $content = $response->content();

                    yield JsonRpcResponse::notification(
                        ...$content->toArray(),
                    );

                    continue;
                }

                $pendingResponses[] = $response;
            }
        } catch (ValidationException $validationException) {
            yield $this->toJsonRpcResponse(
                $request,
                Response::error($validationException->getMessage()),
                $serializable,
            );
        }

        yield $this->toJsonRpcResponse($request, $pendingResponses, $serializable);
    }

    protected function isBinary(string $content): bool
    {
        return str_contains($content, "\0");
    }

    /**
     * @param  array<int, Response|ResponseFactory|string>|Response|ResponseFactory|string  $response
     */
    private function toResponseFactory(Response|ResponseFactory|array|string $response): ResponseFactory
    {
        $responseFactory = is_array($response) && count($response) === 1
            ? Arr::first($response)
            : $response;

        if ($responseFactory instanceof ResponseFactory) {
            return $responseFactory;
        }

        $responses = collect(Arr::wrap($responseFactory))
            ->map(function ($item): mixed {
                if ($item instanceof Response) {
                    return $item;
                }

                return $this->isBinary($item)
                    ? Response::blob($item)
                    : Response::text($item);
            });

        return new ResponseFactory($responses->all());
    }
}
