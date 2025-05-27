<?php

namespace Laravel\Mcp\Transport;

use InvalidArgumentException;

class JsonRpcMessage
{
    public function __construct(
        public ?int $id,
        public string $method,
        public array $params,
    ) {
    }

    public static function fromJson(string $jsonString): self
    {
        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON provided.');
        }

        if (! isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            throw new InvalidArgumentException('Invalid JSON-RPC version. Must be "2.0".');
        }

        if (array_key_exists('id', $data) && $data['id'] !== null && ! is_int($data['id'])) {
            throw new InvalidArgumentException('Invalid "id". Must be an integer or null if present.');
        }

        if (! isset($data['method']) || ! is_string($data['method'])) {
            throw new InvalidArgumentException('Invalid or missing "method". Must be a string.');
        }

        return new static(
            id: $data['id'] ?? null,
            method: $data['method'],
            params: $data['params'] ?? []
        );
    }
}
