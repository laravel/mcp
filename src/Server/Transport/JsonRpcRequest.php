<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Transport\JsonRpcRequest as BaseJsonRpcRequest;

class JsonRpcRequest extends BaseJsonRpcRequest
{
    /**
     * @param  array{id: mixed, jsonrpc?: mixed, method?: mixed, params?: array<string, mixed>}  $jsonRequest
     *
     * @throws JsonRpcException
     */
    public static function from(array $jsonRequest, ?string $sessionId = null): static
    {
        $requestId = $jsonRequest['id'];

        if (! is_int($jsonRequest['id']) && ! is_string($jsonRequest['id'])) {
            throw new JsonRpcException('Invalid Request: The [id] member must be a string, number.', -32600, $requestId);
        }

        if (! isset($jsonRequest['jsonrpc']) || $jsonRequest['jsonrpc'] !== '2.0') {
            throw new JsonRpcException('Invalid Request: The [jsonrpc] member must be exactly [2.0].', -32600, $requestId);
        }

        if (! isset($jsonRequest['method']) || ! is_string($jsonRequest['method'])) {
            throw new JsonRpcException('Invalid Request: The [method] member is required and must be a string.', -32600, $requestId);
        }

        return new static(
            id: $requestId,
            method: $jsonRequest['method'],
            params: $jsonRequest['params'] ?? [],
            sessionId: $sessionId,
        );
    }
}
