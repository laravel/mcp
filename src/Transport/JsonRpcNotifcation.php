<?php

namespace Laravel\Mcp\Transport;

class JsonRpcNotifcation
{
    public function __construct(private string $method, private array $params)
    {
    }

    public static function create(string $method, array $params): JsonRpcNotifcation
    {
        return new static(
            method: $method,
            params: $params,
        );
    }

    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $this->method,
            'params' => $this->params,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}