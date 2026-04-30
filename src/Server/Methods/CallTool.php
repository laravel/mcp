<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Closure;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Events\InvokingTool;
use Laravel\Mcp\Events\ToolInvoked;
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

        $events = Container::getInstance()->make(Dispatcher::class);
        $toolRequest = $request->toRequest();
        $invocationId = (string) Str::uuid7();

        $events->dispatch(new InvokingTool(
            invocationId: $invocationId,
            tool: $tool,
            request: $toolRequest,
        ));

        try {
            // @phpstan-ignore-next-line
            $response = Container::getInstance()->call([$tool, 'handle']);
        } catch (AuthenticationException|AuthorizationException $authException) {
            $response = Response::error($authException->getMessage());
        } catch (ValidationException $validationException) {
            $response = Response::error(ValidationMessages::from($validationException));
        }

        $dispatchInvoked = fn (mixed $finalResponse): mixed => tap($finalResponse, fn () => $events->dispatch(new ToolInvoked(
            invocationId: $invocationId,
            tool: $tool,
            request: $toolRequest,
            response: $finalResponse,
        )));

        if ($response instanceof Generator) {
            return $this->toJsonRpcStreamedResponse(
                $request,
                $this->collectStreamedResponses($response, $dispatchInvoked),
                $this->serializable($tool),
            );
        }

        $dispatchInvoked($response);

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($tool))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($tool));
    }

    /**
     * @param  Generator<int, Response|ResponseFactory|string>  $stream
     * @param  Closure(array<int, Response|ResponseFactory|string>): mixed  $afterCompleted
     * @return Generator<int, Response|ResponseFactory|string>
     */
    protected function collectStreamedResponses(Generator $stream, Closure $afterCompleted): Generator
    {
        $collected = [];

        try {
            foreach ($stream as $key => $value) {
                $collected[] = $value;

                yield $key => $value;
            }
        } finally {
            $afterCompleted($collected);
        }
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
