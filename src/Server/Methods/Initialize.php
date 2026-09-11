<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class Initialize implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $requested = $request->params['protocolVersion'] ?? null;

        return JsonRpcResponse::result($request->id, [
            'protocolVersion' => in_array($requested, ProtocolVersion::initializeSupported(), true)
                ? $requested
                : ProtocolVersion::initializeSupported()[0],
            'capabilities' => $context->serverCapabilities ?: (object) [],
            'serverInfo' => $context->implementation->toArray(),
            'instructions' => $context->instructions,
        ]);
    }
}
