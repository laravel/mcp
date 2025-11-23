<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
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
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        if (is_null($request->get('name'))) {
            throw new JsonRpcException(
                'Missing [name] parameter.',
                -32602,
                $request->id,
            );
        }

        $tool = $context
            ->tools()
            ->first(
                fn ($tool): bool => $tool->name() === $request->params['name'],
                fn () => throw new JsonRpcException(
                    "Tool [{$request->params['name']}] not found.",
                    -32602,
                    $request->id,
                ));

        try {
            // @phpstan-ignore-next-line
            $response = Container::getInstance()->call([$tool, 'handle']);
        } catch (ValidationException $validationException) {
            $response = Response::error(ValidationMessages::from($validationException));
        }

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($tool))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($tool));
    }

    /**
     * @return callable(ResponseFactory): array<string, mixed>
     */
    protected function serializable(Tool $tool): callable
    {
        return fn (ResponseFactory $factory): array => $factory->mergeStructuredContent(
            $factory->mergeMeta([
                'content' => $factory->responses()->map(fn (Response $response): array => $response->content()->toTool($tool))->all(),
                'isError' => $factory->responses()->contains(fn (Response $response): bool => $response->isError()),
            ])
        );
    }
}
