<?php

declare(strict_types=1);

namespace Laravel\Mcp\Transport;

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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $this->method,
            ...($this->params !== [] ? ['params' => $this->params] : []),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray()) ?: '';
    }
}
