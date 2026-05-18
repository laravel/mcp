<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class Initialize implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $requestedVersion = $request->params['protocolVersion'] ?? null;

        if (! is_null($requestedVersion) && ! in_array($requestedVersion, $context->supportedProtocolVersions, true)) {
            throw new JsonRpcException(
                message: 'Unsupported protocol version',
                code: -32602,
                requestId: $request->id,
                data: [
                    'supported' => $context->supportedProtocolVersions,
                    'requested' => $requestedVersion,
                ]
            );
        }

        $protocolVersion = $requestedVersion ?? $context->supportedProtocolVersions[0];

        return JsonRpcResponse::result($request->id, [
            'protocolVersion' => $protocolVersion,
            'capabilities' => $context->serverCapabilities,
            'serverInfo' => $context->implementation->toArray(),
            'instructions' => $context->instructions,
        ]);
    }
}
