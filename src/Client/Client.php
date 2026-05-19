<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Exceptions\ClientException;
use Laravel\Mcp\Client\Methods\Initialize;
use Laravel\Mcp\Client\Methods\Ping;
use Laravel\Mcp\Client\Transport\StdioTransport;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Schema\Implementation;
use Laravel\Mcp\Transport\JsonRpcNotification;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Throwable;

class Client
{
    public bool $connected = false;

    public ?string $protocolVersion = null;

    public ?object $serverCapabilities = null;

    public ?Implementation $serverInfo = null;

    public ?string $instructions = null;

    protected int $nextRequestId = 1;

    public function __construct(
        protected Transport $transport,
        protected float $timeoutSeconds = 30.0,
        public Implementation $clientInfo = new Implementation(
            name: 'Laravel MCP Client',
            version: '0.0.1',
        ),
    ) {
        //
    }

    /**
     * @param  array<int, string>  $args
     */
    public static function local(string $command, array $args = [], float $timeoutSeconds = 30.0): static
    {
        return new static(new StdioTransport($command, $args), $timeoutSeconds);
    }

    public function connect(): static
    {
        if ($this->connected) {
            return $this;
        }

        $this->transport->connect();

        try {
            $this->storeInitializeResult($this->call(new Initialize($this->clientInfo)));
            $this->notify('notifications/initialized');
        } catch (Throwable $throwable) {
            $this->transport->disconnect();

            throw $throwable;
        }

        $this->connected = true;

        return $this;
    }

    public function disconnect(): void
    {
        $this->transport->disconnect();

        $this->connected = false;
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
        $deadline = microtime(true) + $this->timeoutSeconds;

        do {
            $remaining = $deadline - microtime(true);

            if ($remaining <= 0) {
                throw new ClientException('Timed out while waiting for server response.');
            }

            $raw = $this->transport->receive($remaining);
            $response = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($response)) {
                throw new ClientException('Invalid JSON-RPC response from server.');
            }

            $this->handleServerRequest($response);
        } while (($response['id'] ?? null) !== $request->id);

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

    protected function storeInitializeResult(mixed $result): void
    {
        $protocolVersion = is_array($result) ? ($result['protocolVersion'] ?? null) : null;
        $capabilities = is_array($result) ? ($result['capabilities'] ?? null) : null;
        $serverInfo = is_array($result) ? ($result['serverInfo'] ?? null) : null;

        if (! is_string($protocolVersion)
            || ! in_array($protocolVersion, ProtocolVersion::supported(), true)
            || ! is_array($capabilities)
            || ! is_array($serverInfo)
            || ! is_string($serverInfo['name'] ?? null)
            || ! is_string($serverInfo['version'] ?? null)) {
            throw new ClientException('Invalid initialize response from server.');
        }

        $instructions = $result['instructions'] ?? null;

        $this->protocolVersion = $protocolVersion;
        $this->serverCapabilities = (object) $capabilities;
        $this->serverInfo = Implementation::fromArray($serverInfo);
        $this->instructions = is_string($instructions) ? $instructions : null;
    }

    protected function notify(string $method): void
    {
        $notification = new JsonRpcNotification($method, []);

        $this->transport->send($notification->toJson());
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    protected function handleServerRequest(array $frame): void
    {
        $id = $frame['id'] ?? null;
        $method = $frame['method'] ?? null;

        if (! is_string($method) || (! is_int($id) && ! is_string($id))) {
            return;
        }

        if ($method === 'ping') {
            $response = JsonRpcResponse::result($id, []);

            $this->transport->send($response->toJson());
        }
    }

    public function __destruct()
    {
        if ($this->connected) {
            $this->disconnect();
        }
    }
}
