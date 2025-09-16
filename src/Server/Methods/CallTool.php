<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

class CallTool implements Errable, Method
{
    use InteractsWithResponses;

    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $jsonRpcRequest, ServerContext $context): Generator|JsonRpcResponse
    {
        if (is_null($jsonRpcRequest->get('name'))) {
            throw new JsonRpcException(
                'Missing [name] parameter.',
                -32602,
                $jsonRpcRequest->id,
            );
        }

        $request = $jsonRpcRequest->toRequest();

        $tool = $context
            ->tools($request)
            ->first(
                fn ($tool): bool => $tool->name() === $jsonRpcRequest->params['name'],
                fn () => throw new JsonRpcException(
                    "Tool [{$jsonRpcRequest->params['name']}] not found.",
                    -32602,
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
            ? $this->toJsonRpcStreamedResponse($jsonRpcRequest, $response, $this->serializable($tool))
            : $this->toJsonRpcResponse($jsonRpcRequest, $response, $this->serializable($tool));
    }

    /**
     * @return callable(Collection<int, Response>): array{content: array<int, array<string, mixed>>, isError: bool}
     */
    protected function serializable(Tool $tool): callable
    {
        return fn (Collection $responses): array => [
            'content' => $responses->map(fn (Response $response): array => $response->content()->toTool($tool))->all(),
            'isError' => $responses->contains(fn (Response $response): bool => $response->isError()),
        ];
    }
}
