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
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

class GetPrompt implements Method
{
    use InteractsWithResponses;

    /**
     * @return Generator<JsonRpcResponse>|JsonRpcResponse
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $jsonRpcRequest, ServerContext $context): Generator|JsonRpcResponse
    {
        if (is_null($jsonRpcRequest->get('name'))) {
            throw new JsonRpcException(
                'Missing [name] parameter.',
                -32601,
                $jsonRpcRequest->id,
            );
        }

        $request = $jsonRpcRequest->toRequest();

        $prompt = $context->prompts($request)
            ->first(
                fn ($prompt): bool => $prompt->name() === $jsonRpcRequest->get('name'),
                fn () => throw new JsonRpcException(
                    "Prompt [{$jsonRpcRequest->get('name')}] not found.",
                    -32601,
                    $jsonRpcRequest->id,
                ));

        try {
            // @phpstan-ignore-next-line
            $response = Container::getInstance()->call([$prompt, 'handle'], [
                'request' => $request,
            ]);
        } catch (ValidationException $validationException) {
            $response = Response::error('Invalid params: '.ValidationMessages::from($validationException));
        }

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($jsonRpcRequest, $response, $this->serializable($prompt))
            : $this->toJsonRpcResponse($jsonRpcRequest, $response, $this->serializable($prompt));
    }

    /**
     * @return callable(Collection<int, Response>): array{description?: string, messages: array<int, array{role: string, content: array<int, array<string, mixed>}>}
     */
    protected function serializable(Prompt $prompt): callable
    {
        return fn (Collection $responses): array => [
            'description' => $prompt->description(),
            'messages' => $responses->map(fn (Response $response): array => [
                'role' => $response->role()->value,
                'content' => $response->content()->toArray(),
            ])->all(),
        ];
    }
}
