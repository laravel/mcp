<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Illuminate\Contracts\Support\Arrayable;

class JsonRpcNotification extends JsonRpcResponse
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(protected string $method, protected array $params)
    {
        //
    }

    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>  $params
     */
    public static function create(string $method, array|Arrayable $params): JsonRpcNotification
    {
        return new static(
            method: $method,
            params: is_array($params) ? $params : $params->toArray(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $this->method,
            'params' => $this->params,
        ];
    }
}
