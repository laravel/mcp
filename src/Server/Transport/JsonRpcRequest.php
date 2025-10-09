<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Resources\Uri;

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

    public function toRequest(): Request
    {
        $pathVariables = [];

        // We only add the path variables to the request if the method
        // is 'resources/read' since this is the only method that
        // could potentially have variables defined on its URI
        if ($this->method === 'resources/read' && ($uriTemplate = $this->get('uriTemplate')) !== null && ($uri = $this->get('uri')) !== null) {
            $pathVariables = Uri::pathVariables($uriTemplate, $uri);
        }

        return new Request(
            [
                ...($this->params['arguments'] ?? []),
                ...$pathVariables,
            ],
            $this->sessionId
        );
    }
}
