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
     *
     * @throws JsonRpcException
     */
    protected function toJsonRpcResponse(JsonRpcRequest $request, array|Response|ResponseFactory|string $response, callable $serializable): JsonRpcResponse
    {
        if (! ($response instanceof ResponseFactory)) {
            $responses = collect(Arr::wrap($response))->map(fn ($item): Response => $item instanceof Response
                ? $item
                : ($this->isBinary($item) ? Response::blob($item) : Response::text($item))
            );

            $response = ResponseFactory::make($responses->all());
        }

        $response->responses()->each(function (Response $response) use ($request): void {
            if (! $this instanceof Errable && $response->isError()) {
                throw new JsonRpcException(
                    $response->content()->__toString(), // @phpstan-ignore-line
                    -32603,
                    $request->id,
                );
            }
        });

        return JsonRpcResponse::result($request->id, $serializable($response));
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
}
