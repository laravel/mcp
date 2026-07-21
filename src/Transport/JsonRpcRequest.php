<?php

declare(strict_types=1);

namespace Laravel\Mcp\Transport;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Request;

class JsonRpcRequest
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public int|string $id,
        public string $method,
        public array $params,
        public ?string $sessionId = null
    ) {
        //
    }

    /**
     * @param  array{id: mixed, jsonrpc?: mixed, method?: mixed, params?: mixed}  $jsonRequest
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

        if (array_key_exists('params', $jsonRequest) && ! self::isObject($jsonRequest['params'])) {
            throw new JsonRpcException('Invalid params: The [params] member must be an object.', -32602, $requestId);
        }

        return new static(
            id: $requestId,
            method: $jsonRequest['method'],
            params: $jsonRequest['params'] ?? [],
            sessionId: $sessionId,
        );
    }

    private static function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }

    public function cursor(): ?string
    {
        return $this->get('cursor');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        return isset($this->params['_meta']) && is_array($this->params['_meta']) ? $this->params['_meta'] : null;
    }

    public function toRequest(): Request
    {
        if (array_key_exists('arguments', $this->params)) {
            $arguments = $this->params['arguments'];

            if (! self::isObject($arguments)) {
                throw new JsonRpcException('Invalid params: The [arguments] member must be an object.', -32602, $this->id);
            }
        } else {
            $arguments = [];
        }

        return new Request($arguments, $this->sessionId, $this->meta());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $this->id,
            'method' => $this->method,
            ...$this->params === [] ? [] : ['params' => $this->params],
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
