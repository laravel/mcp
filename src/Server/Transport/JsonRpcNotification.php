<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Illuminate\Contracts\Support\Arrayable;

class JsonRpcNotification
{
    /**
     * Create a new JSON-RPC notification response.
     */
    public function __construct(private string $method, private array $params) {}

    /**
     * Create a new JSON-RPC notification response.
     */
    public static function create(string $method, array|Arrayable $params): JsonRpcNotification
    {
        return new static(
            method: $method,
            params: is_array($params) ? $params : $params->toArray(),
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
