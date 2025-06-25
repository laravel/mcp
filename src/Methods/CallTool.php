<?php

namespace Laravel\Mcp\Methods;

use Generator;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Tools\ToolNotification;
use Laravel\Mcp\Tools\ToolResult;
use Laravel\Mcp\Transport\JsonRpcNotification;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

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
            $tool = collect($context->tools)
                ->map(fn ($tool) => is_string($tool) ? app($tool) : $tool)
                ->firstOrFail(fn ($tool) => $tool->name() === $request->params['name']);
        } catch (ItemNotFoundException $e) {
            return JsonRpcResponse::create(
                $request->id,
                // CAST THIS TO ARRAY AUTOMATICALLY
                ToolResult::error('Tool not found')->toArray()
            );
        }

        try {
            $result = $tool->handle($request->params['arguments']);
        } catch (ValidationException $e) {
            return JsonRpcResponse::create(
                $request->id,
                ToolResult::error($e->getMessage())->toArray()
            );
        }

        return $result instanceof Generator
            ? $this->toStream($request, $result)
            : $this->toResponse($request->id, $result->toArray());
    }

    /**
     * Convert the result to a JSON-RPC response.
     */
    private function toResponse(string $id, array $result): JsonRpcResponse
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
                            $response->toArray()
                        );

                        continue;
                    }

                    yield $this->toResponse($request->id, $response->toArray());
                }
            } catch (ValidationException $e) {
                yield $this->toResponse($request->id, ToolResult::error($e->getMessage())->toArray());
            }
        })();
    }
}
