<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;

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
     * @param  array{id: mixed, jsonrpc?: mixed, method?: mixed, params?: array<string, mixed>}  $jsonRequest
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

        return new static(
            id: $requestId,
            method: $jsonRequest['method'],
            params: $jsonRequest['params'] ?? [],
            sessionId: $sessionId,
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

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        return isset($this->params['_meta']) && is_array($this->params['_meta']) ? $this->params['_meta'] : null;
    }

    public function toRequest(): Request
    {
        return new Request($this->params['arguments'] ?? [], $this->sessionId, $this->meta());
    }
}
