<?php

declare(strict_types=1);

namespace Laravel\Mcp\Transport;

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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $this->id,
            'method' => $this->method,
            ...($this->params !== [] ? ['params' => $this->params] : []),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray()) ?: '';
    }
}
