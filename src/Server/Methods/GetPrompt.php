<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use Laravel\Mcp\Server\Methods\Concerns\ResolvesPrompts;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

class GetPrompt implements Method
{
    use InteractsWithResponses;
    use ResolvesPrompts;

    /**
     * @return Generator<JsonRpcResponse>|JsonRpcResponse
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        try {
            $prompt = $this->resolvePrompt($request->get('name'), $context);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new JsonRpcException($invalidArgumentException->getMessage(), -32602, $request->id);
        }

        try {
            // @phpstan-ignore-next-line
            $response = Container::getInstance()->call([$prompt, 'handle']);
        } catch (ValidationException $validationException) {
            $response = Response::error('Invalid params: '.ValidationMessages::from($validationException));
        }

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($prompt))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($prompt));
    }

    /**
     * @return callable(ResponseFactory): array<string, mixed>
     */
    protected function serializable(Prompt $prompt): callable
    {
        return fn (ResponseFactory $factory): array => $factory->mergeMeta([
            'description' => $prompt->description(),
            'messages' => $factory->responses()->map(fn (Response $response): array => [
                'role' => $response->role()->value,
                'content' => $response->content()->toPrompt($prompt),
            ])->all(),
        ]);
    }
}
