<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tools\ToolNotification;
use Laravel\Mcp\Server\Tools\ToolResult;
use Laravel\Mcp\Server\Transport\JsonRpcNotification;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

class CallTool implements Method
{
    /**
     * @return JsonRpcResponse|Generator<JsonRpcNotification|JsonRpcResponse>
     */
    public function handle(JsonRpcRequest $request, ServerContext $context)
    {
        try {
            $tool = $context->tools()
                ->firstOrFail(fn ($tool) => $tool->name() === $request->params['name']);
        } catch (ItemNotFoundException $e) {
            return JsonRpcResponse::create(
                $request->id,
                ToolResult::error('Tool not found')
            );
        }

        try {
            $result = Container::getInstance()->call([$tool, 'handle'], [
                'request' => new Request(
                    $request->params['arguments'],
                ),
            ]);
        } catch (ValidationException $e) {
            $result = ToolResult::error(ValidationMessages::from($e));
        }

        return $result instanceof Generator
            ? $this->toStream($request, $result)
            : $this->toResponse($request->id, $result);
    }

    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>  $result
     */
    protected function toResponse(?int $id, array|Arrayable|string $result): JsonRpcResponse
    {
        if (is_string($result)) {
            $result = ToolResult::text($result);
        }

        return JsonRpcResponse::create($id, $result);
    }

    protected function toStream(JsonRpcRequest $request, Generator $result): Generator
    {
        return (function () use ($result, $request) {
            try {
                foreach ($result as $response) {
                    if ($response instanceof ToolNotification) {
                        yield JsonRpcNotification::create(
                            $response->getMethod(),
                            $response
                        );

                        continue;
                    }

                    yield $this->toResponse($request->id, $response);
                }
            } catch (ValidationException $e) {
                yield $this->toResponse($request->id, ToolResult::error($e->getMessage()));
            }
        })();
    }
}
