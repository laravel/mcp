<?php

declare(strict_types=1);

namespace Laravel\Mcp\Transport;

use Laravel\Mcp\Exceptions\JsonRpcException;

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
     * @param  array{jsonrpc?: mixed, method?: mixed, params?: mixed}  $jsonRequest
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

        if (array_key_exists('params', $jsonRequest) && ! self::isObject($jsonRequest['params'])) {
            throw new JsonRpcException('Invalid params: The [params] member must be an object.', -32602);
        }

        return new static(
            method: $jsonRequest['method'],
            params: $jsonRequest['params'] ?? []
        );
    }

    private static function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $this->method,
            ...$this->params === [] ? [] : ['params' => $this->params],
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
