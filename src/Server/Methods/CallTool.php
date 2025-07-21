<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Contracts\Methods\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tools\ToolNotification;
use Laravel\Mcp\Server\Tools\ToolResult;
use Laravel\Mcp\Server\Transport\JsonRpcNotification;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class CallTool implements Method
{
    /**
     * Handle the JSON-RPC tool/call request.
     *
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
            $result = $tool->handle($request->params['arguments']);
        } catch (ValidationException $e) {
            return JsonRpcResponse::create(
                $request->id,
                ToolResult::error($e->getMessage())
            );
        }

        return $result instanceof Generator
            ? $this->toStream($request, $result)
            : $this->toResponse($request->id, $result);
    }

    /**
     * Convert the result to a JSON-RPC response.
     */
    private function toResponse(?int $id, array|Arrayable $result): JsonRpcResponse
    {
        return JsonRpcResponse::create($id, $result);
    }

    /**
     * Convert the result to a JSON-RPC stream.
     */
    private function toStream(JsonRpcRequest $request, Generator $result): Generator
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
