<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Illuminate\Support\Arr;
use Laravel\Mcp\Enums\ProtocolVersion;
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
        $negotiated = ProtocolVersion::from($protocolVersion);

        $serverInfo = $context->implementation->toArray();

        if (! $negotiated->supportsImplementationMetadata()) {
            $serverInfo = Arr::except($serverInfo, ['icons', 'description', 'websiteUrl']);
        }

        $initResult = [
            'protocolVersion' => $protocolVersion,
            'capabilities' => $context->serverCapabilities,
            'serverInfo' => $serverInfo,
            'instructions' => $context->instructions,
        ];

        if (! $negotiated->supportsInstructions()) {
            $initResult = Arr::except($initResult, 'instructions');
        }

        return JsonRpcResponse::result($request->id, $initResult);
    }
}
