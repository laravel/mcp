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
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

class CallTool implements Method
{
    use InteractsWithResponses;

    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $jsonRpcRequest, ServerContext $context): Generator|JsonRpcResponse
    {
        $request = $jsonRpcRequest->toRequest();

        $tool = $context
            ->tools($request)
            ->first(
                fn ($tool): bool => $tool->name() === $jsonRpcRequest->params['name'],
                fn () => throw new JsonRpcException(
                    "Tool [{$jsonRpcRequest->params['name']}] not found.",
                    -32601,
                    $jsonRpcRequest->id,
                ));

        try {
            // @phpstan-ignore-next-line
            $response = Container::getInstance()->call([$tool, 'handle'], [
                'request' => $request,
            ]);
        } catch (ValidationException $validationException) {
            $response = Response::error(ValidationMessages::from($validationException));
        }

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($jsonRpcRequest, $response, $this->serializable())
            : $this->toJsonRpcResponse($jsonRpcRequest, $response, $this->serializable());
    }

    /**
     * @return callable(Collection<int, Response>): array{content: array<int, array<string, mixed>>, isError: bool}
     */
    protected function serializable(): callable
    {
        return fn (Collection $responses): array => [
            'content' => $responses->map(fn (Response $response): array => $response->content()->toArray())->all(),
            'isError' => $responses->contains(fn (Response $response): bool => $response->isError()),
        ];
    }
}
