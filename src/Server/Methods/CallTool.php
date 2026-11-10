<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\ToolInvoker;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class CallTool implements Errable, Method
{
    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $name = $request->getString('name');

        if (is_null($name)) {
            throw new JsonRpcException(
                'Missing [name] parameter.',
                -32602,
                $request->id,
            );
        }

        $tool = $context
            ->tools()
            ->first(
                fn ($tool): bool => $tool->name() === $name,
                fn (): never => throw new JsonRpcException(
                    "Tool [{$name}] not found.",
                    -32602,
                    $request->id,
                ));

        return (new ToolInvoker)->invoke($tool, $request);
    }
}
