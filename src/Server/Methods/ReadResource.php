<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ServerContext;
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

        $resource = $context->resources()
            ->first(
                fn (Resource $resource): bool => $resource->uri() === $request->get('uri'),
                fn () => throw new JsonRpcException(
                    "Resource [{$request->get('uri')}] not found.",
                    -32002,
                    $request->id,
                ));

        try {
            // @phpstan-ignore-next-line
            $response = Container::getInstance()->call([$resource, 'handle']);
        } catch (ValidationException $validationException) {
            $response = Response::error('Invalid params: '.ValidationMessages::from($validationException));
        }

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($resource))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($resource));
    }

    protected function serializable(Resource $resource): callable
    {
        return fn (Collection $responses): array => [
            'contents' => $responses->map(fn (Response $response): array => $response->content()->toResource($resource))->all(),
        ];
    }
}
