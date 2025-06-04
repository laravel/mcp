<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;

class Initialize implements Method
{
    public function handle(JsonRpcMessage $message, SessionContext $session, ServerContext $context): JsonRpcResponse
    {
        $requestedVersion = $message->params['protocolVersion'] ?? null;

        if (! is_null($requestedVersion) && ! in_array($requestedVersion, $context->supportedProtocolVersions)) {
            throw new JsonRpcException(
                message: 'Unsupported protocol version',
                code: -32602,
                requestId: $message->id,
                data: [
                    'supported' => $context->supportedProtocolVersions,
                    'requested' => $requestedVersion,
                ]
            );
        }

        $protocol = $requestedVersion ?? $context->supportedProtocolVersions[0];

        return JsonRpcResponse::create($message->id, [
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
