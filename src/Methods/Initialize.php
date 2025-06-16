<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcRequest;

class Initialize implements Method
{
    /**
     * Handle the JSON-RPC initialize request.
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $requestedVersion = $request->params['protocolVersion'] ?? null;

        if (! is_null($requestedVersion) && ! in_array($requestedVersion, $context->supportedProtocolVersions)) {
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

        $protocol = $requestedVersion ?? $context->supportedProtocolVersions[0];

        return JsonRpcResponse::create($request->id, [
            'protocolVersion' => $protocol,
            'capabilities' => $context->serverCapabilities,
            'serverInfo' => [
                'name' => $context->serverName,
                'version' => $context->serverVersion,
            ],
            'instructions' => $context->instructions,
        ]);
    }
}
