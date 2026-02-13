<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport;

use Illuminate\Http\Client\Factory;
use Laravel\Mcp\Client\Contracts\ClientTransport;
use Laravel\Mcp\Client\Exceptions\ClientException;
use Laravel\Mcp\Client\Exceptions\ConnectionException;

class HttpClientTransport implements ClientTransport
{
    protected bool $connected = false;

    protected ?string $sessionId = null;

    protected Factory $http;

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        protected string $url,
        protected array $headers = [],
        protected float $timeout = 30,
        ?Factory $httpFactory = null,
    ) {
        $this->http = $httpFactory ?? new Factory;
    }

    public function connect(): void
    {
        $this->connected = true;
    }

    public function send(string $message): string
    {
        $this->ensureConnected();

        $response = $this->http
            ->timeout((int) $this->timeout)
            ->withHeaders($this->buildHeaders())
            ->withBody($message, 'application/json')
            ->post($this->url);

        if ($response->header('MCP-Session-Id')) {
            $this->sessionId = $response->header('MCP-Session-Id');
        }

        if (! $response->successful()) {
            throw new ClientException("HTTP request failed with status {$response->status()}.");
        }

        return $response->body();
    }

    public function notify(string $message): void
    {
        $this->ensureConnected();

        $response = $this->http
            ->timeout((int) $this->timeout)
            ->withHeaders($this->buildHeaders())
            ->withBody($message, 'application/json')
            ->post($this->url);

        if ($response->header('MCP-Session-Id')) {
            $this->sessionId = $response->header('MCP-Session-Id');
        }
    }

    public function disconnect(): void
    {
        $this->connected = false;
        $this->sessionId = null;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        $headers = array_merge($this->headers, [
            'Accept' => 'application/json, text/event-stream',
        ]);

        if ($this->sessionId !== null) {
            $headers['MCP-Session-Id'] = $this->sessionId;
        }

        return $headers;
    }

    protected function ensureConnected(): void
    {
        if (! $this->connected) {
            throw new ConnectionException('Not connected.');
        }
    }
}
