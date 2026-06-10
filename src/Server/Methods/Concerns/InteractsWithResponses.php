<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods\Concerns;

use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Support\ValidationMessages;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Throwable;

trait InteractsWithResponses
{
    /**
     * @param  array<int, Response|ResponseFactory|string>|Response|ResponseFactory|string  $response
     *
     * @throws JsonRpcException
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
        } catch (Throwable $throwable) {
            if ($this instanceof Errable) {
                yield $this->toJsonRpcResponse(
                    $request,
                    $this->toErrorResponse($throwable),
                    $serializable,
                );

                return;
            }

            throw $this->toJsonRpcException($throwable, $request->id);
        }

        yield $this->toJsonRpcResponse($request, $pendingResponses, $serializable);
    }

    protected function callHandler(callable $handler, JsonRpcRequest $request): mixed
    {
        try {
            return $handler();
        } catch (Throwable $throwable) {
            if ($this instanceof Errable) {
                return $this->toErrorResponse($throwable);
            }

            throw $this->toJsonRpcException($throwable, $request->id);
        }
    }

    protected function toJsonRpcException(Throwable $e, mixed $requestId): JsonRpcException
    {
        if ($e instanceof ValidationException) {
            return new JsonRpcException(ValidationMessages::from($e), -32602, $requestId);
        }

        return new JsonRpcException($this->toErrorMessage($e), -32603, $requestId);
    }

    protected function toErrorResponse(Throwable $e): Response
    {
        if ($e instanceof ValidationException) {
            return Response::error(ValidationMessages::from($e));
        }

        if ($e instanceof AuthenticationException || $e instanceof AuthorizationException) {
            return Response::error($e->getMessage());
        }

        return Response::error($this->toErrorMessage($e));
    }

    protected function toErrorMessage(Throwable $e): string
    {
        if (config('app.debug', false)) {
            return $e->getMessage();
        }

        report($e);

        return 'An internal server error occurred.';
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

        $items = is_array($responseFactory) ? $responseFactory : [$responseFactory];

        $responses = collect($items)
            ->map(function ($item): Response {
                if ($item instanceof Response) {
                    return $item;
                }

                if (! is_string($item)) {
                    throw new InvalidArgumentException('Response must be a Response instance or string');
                }

                return $this->isBinary($item)
                    ? Response::blob($item)
                    : Response::text($item);
            });

        return new ResponseFactory($responses->all());
    }
}
