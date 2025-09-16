<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods\Concerns;

use Generator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

trait InteractsWithResponses
{
    /**
     * @param  array<int, Response|string>|Response|string  $response
     */
    protected function toJsonRpcResponse(JsonRpcRequest $request, array|Response|string $response, callable $serializable): JsonRpcResponse
    {
        $responses = collect(
            is_array($response) ? $response : [$response]
        )->map(fn (Response|string $response): Response => $response instanceof Response
            ? $response
            : ($this->isBinary($response) ? Response::blob($response) : Response::text($response))
        );

        $responses->each(function (Response $response) use ($request): void {
            if (! $this instanceof Errable && $response->isError()) {
                throw new JsonRpcException(
                    // @phpstan-ignore-next-line
                    $response->content()->__toString(),
                    -32603,
                    $request->id,
                );
            }
        });

        return JsonRpcResponse::result($request->id, $serializable($responses));
    }

    /**
     * @param  iterable<Response|string>  $responses
     * @return Generator<JsonRpcResponse>
     */
    protected function toJsonRpcStreamedResponse(JsonRpcRequest $request, iterable $responses, callable $serializable): Generator
    {
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
