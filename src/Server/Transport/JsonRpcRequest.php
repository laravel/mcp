<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Laravel\Mcp\Server\Exceptions\JsonRpcException;

class JsonRpcRequest
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public int|string|null $id,
        public string $method,
        public array $params,
    ) {
        //
    }

    /**
     * @throws JsonRpcException
     */
    public static function fromJson(string $jsonString): static
    {
        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new JsonRpcException('Parse error.', -32700, null);
        }

        $requestId = $data['id'] ?? null;

        if (! isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            throw new JsonRpcException('Invalid Request: Invalid JSON-RPC version. Must be "2.0".', -32600, $requestId);
        }

        if (array_key_exists('id', $data) && $data['id'] !== null && ! is_int($data['id']) && ! is_string($data['id'])) {
            throw new JsonRpcException('Invalid params: "id" must be an integer, string, or null if present.', -32602, $requestId);
        }

        if (! isset($data['method']) || ! is_string($data['method'])) {
            throw new JsonRpcException('Invalid Request: Invalid or missing "method". Must be a string.', -32600, $requestId);
        }

        return new static(
            id: $requestId,
            method: $data['method'],
            params: $data['params'] ?? []
        );
    }

    public function cursor(): ?string
    {
        return $this->get('cursor');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
