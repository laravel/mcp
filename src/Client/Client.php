<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Exceptions\ClientException;
use Laravel\Mcp\Client\Methods\Initialize;
use Laravel\Mcp\Client\Methods\Ping;
use Laravel\Mcp\Client\Transport\StdioTransport;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Transport\JsonRpcNotification;
use Laravel\Mcp\Transport\JsonRpcRequest;

class Client
{
    protected bool $connected = false;

    protected int $nextRequestId = 1;

    protected string $clientName = 'Laravel MCP Client';

    protected string $clientVersion = '0.0.1';

    public function __construct(
        protected Transport $transport,
    ) {
        //
    }

    /**
     * @param  array<int, string>  $args
     */
    public static function stdio(string $command, array $args = []): static
    {
        return new static(new StdioTransport($command, $args));
    }

    public function connect(): static
    {
        if ($this->connected) {
            return $this;
        }

        $this->transport->connect();

        $this->call(new Initialize($this->clientName, $this->clientVersion));

        $this->notify('notifications/initialized');

        $this->connected = true;

        return $this;
    }

    public function disconnect(): void
    {
        $this->transport->disconnect();

        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function ping(): void
    {
        $this->connect();

        $this->call(new Ping);
    }

    /**
     * @return array<string, mixed>
     */
    protected function call(Method $method): array
    {
        $request = new JsonRpcRequest(
            id: $this->nextRequestId++,
            method: $method->method(),
            params: $method->params(),
        );

        $this->transport->send($request->toJson());

        $raw = $this->transport->receive();
        $response = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($response)) {
            throw new ClientException('Invalid JSON-RPC response from server.');
        }

        if (isset($response['error']) && is_array($response['error'])) {
            $message = $response['error']['message'] ?? 'Unknown JSON-RPC error.';
            $code = $response['error']['code'] ?? 0;
            $data = $response['error']['data'] ?? null;

            throw new JsonRpcException(
                is_string($message) ? $message : 'Unknown JSON-RPC error.',
                is_int($code) ? $code : 0,
                $response['id'] ?? null,
                is_array($data) ? $data : null,
            );
        }

        $result = $response['result'] ?? [];

        return is_array($result) ? $result : [];
    }

    protected function notify(string $method): void
    {
        $notification = new JsonRpcNotification($method, []);

        $this->transport->send($notification->toJson());
    }

    public function __destruct()
    {
        if ($this->connected) {
            $this->disconnect();
        }
    }
}
