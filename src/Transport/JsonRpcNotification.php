<?php

namespace Laravel\Mcp\Transport;

class JsonRpcNotification
{
    /**
     * Create a new JSON-RPC notification response.
     */
    public function __construct(private string $method, private array $params) {}

    /**
     * Create a new JSON-RPC notification response.
     */
    public static function create(string $method, array $params): JsonRpcNotification
    {
        return new static(
            method: $method,
            params: $params,
        );
    }

    /**
     * Convert the notification response to an array.
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $this->method,
            'params' => $this->params,
        ];
    }

    /**
     * Convert the notification response to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
