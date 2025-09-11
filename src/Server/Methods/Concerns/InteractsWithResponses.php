<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods\Concerns;

use Generator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

trait InteractsWithResponses
{
    /**
     * @param  array<int, Response>|Response|string  $response
     */
    protected function toJsonRpcResponse(JsonRpcRequest $request, array|Response|string $response, callable $serializable): JsonRpcResponse
    {
        $responses = collect(
            is_array($response) ? $response : [$response]
        )->map(fn (Response|string $response) => $response instanceof Response
            ? $response
            : Response::text($response),
        );

        $serializable($responses);

        return JsonRpcResponse::result($request->id, $serializable($responses));
    }

    /**
     * @param  iterable<Response>  $responses
     * @return Generator<JsonRpcResponse>
     */
    protected function toJsonRpcStreamedResponse(JsonRpcRequest $request, iterable $responses, callable $serializable): Generator
    {
        return (function () use ($responses, $request, $serializable) {
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
        })();
    }
}
