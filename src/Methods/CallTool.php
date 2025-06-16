<?php

namespace Laravel\Mcp\Methods;

use Exception;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Tools\ToolNotification;
use Laravel\Mcp\Tools\ToolResponse;
use Laravel\Mcp\Transport\JsonRpcNotification;
use Generator;

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
                ->firstOrFail(function($tool) use ($request) {
                    if (is_string($tool)) {
                        return (new $tool())->name() === $request->params['name'];
                    }
                    return $tool->name() === $request->params['name'];
                });

            if (is_string($tool)) {
                $tool = new $tool();
            }
        } catch (Exception $e) {
            return JsonRpcResponse::create(
                $request->id,
                (new ToolResponse('Tool not found', true))->toArray()
            );
        }

        try {
            $result = $tool->handle($request->params['arguments']);
        } catch (ValidationException $e) {
            return JsonRpcResponse::create(
                $request->id,
                (new ToolResponse($e->getMessage(), true))->toArray()
            );
        }

        if (! $result instanceof Generator) {
            return $this->toResponse($request->id, $result->toArray());
        }

        return $this->toStream($request, $result);
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
                yield $this->toResponse($request->id, (new ToolResponse($e->getMessage(), true))->toArray());
            }
        })();
    }
}
