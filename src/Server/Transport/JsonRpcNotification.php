<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Laravel\Mcp\Server\Exceptions\JsonRpcException;

class JsonRpcNotification
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public string $method,
        public array $params,
    ) {
        //
    }

    /**
     * @param  array{jsonrpc?: mixed, method?: mixed, params?: array<string, mixed>}  $jsonRequest
     *
     * @throws JsonRpcException
     */
    public static function from(array $jsonRequest): static
    {
        if (! isset($jsonRequest['jsonrpc']) || $jsonRequest['jsonrpc'] !== '2.0') {
            throw new JsonRpcException('Invalid Request: Invalid JSON-RPC version. Must be "2.0".', -32600);
        }

        if (! isset($jsonRequest['method']) || ! is_string($jsonRequest['method'])) {
            throw new JsonRpcException('Invalid Request: Invalid or missing "method". Must be a string.', -32600);
        }

        return new static(
            method: $jsonRequest['method'],
            params: $jsonRequest['params'] ?? []
        );
    }
}
