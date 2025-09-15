<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

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
        $initResult = [
            'protocolVersion' => $protocolVersion,
            'capabilities' => $context->serverCapabilities,
            'serverInfo' => [
                'name' => $context->serverName,
                'version' => $context->serverVersion,
            ],
            'instructions' => $context->instructions,
        ];

        if (in_array($protocolVersion, ['2024-11-05', '2025-03-26'], true)) {
            unset($initResult['instructions']);
        }

        return JsonRpcResponse::result($request->id, $initResult);
    }
}
