<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use JsonException;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Methods\Initialize;
use Laravel\Mcp\Client\Schema\InitializeResult;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Schema\Implementation;
use Laravel\Mcp\Transport\JsonRpcNotification;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Throwable;

class Protocol
{
    protected bool $connected = false;

    protected bool $connecting = false;

    protected int $nextRequestId = 1;

    protected ?InitializeResult $initializeResult = null;

    public function __construct(
        protected Transport $transport,
        protected Implementation $clientInfo,
    ) {
        //
    }

    public function connected(): bool
    {
        return $this->connected;
    }

    public function initializeResult(): ?InitializeResult
    {
        return $this->initializeResult;
    }

    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $this->transport->connect();
        $this->connecting = true;

        try {
            $this->initializeResult = (new Initialize($this->clientInfo))->handle($this);

            $this->notify('notifications/initialized');
        } catch (Throwable $throwable) {
            $this->disconnect();

            throw $throwable;
        } finally {
            $this->connecting = false;
        }

        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;

        $this->transport->disconnect();
    }

    /**
     * @return array<string, mixed>
     */
    public function dispatch(Method $method): array
    {
        if (! $this->connected && ! $this->connecting) {
            $this->connect();
        }

        $request = new JsonRpcRequest(
            id: $this->nextRequestId++,
            method: $method->method(),
            params: $method->params(),
        );

        try {
            $this->transport->send($request->toJson());

            do {
                $raw = $this->transport->receive();

                try {
                    $response = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $jsonException) {
                    throw new ClientException(
                        'Malformed JSON-RPC response from server: '.$jsonException->getMessage(),
                        0,
                        $jsonException,
                    );
                }

                if (! is_array($response) || ($response['jsonrpc'] ?? null) !== '2.0') {
                    throw new ClientException('Invalid JSON-RPC response from server.');
                }

                $this->handleServerRequest($response);
            } while (($response['id'] ?? null) !== $request->id);

            $hasResult = array_key_exists('result', $response);
            $hasError = array_key_exists('error', $response);

            if ($hasResult === $hasError) {
                throw new ClientException('Invalid JSON-RPC response: must contain exactly one of "result" or "error".');
            }

            if ($hasError && ! is_array($response['error'])) {
                throw new ClientException('Invalid JSON-RPC error payload.');
            }
        } catch (Throwable $throwable) {
            if ($this->connected) {
                $this->disconnect();
            }

            throw $throwable;
        }

        if ($hasError) {
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

        return is_array($response['result']) ? $response['result'] : [];
    }

    public function notify(string $method): void
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
            $this->transport->send(JsonRpcResponse::result($id, [])->toJson());

            return;
        }

        $this->transport->send(JsonRpcResponse::error(
            $id,
            -32601,
            "Method [{$method}] not supported by this client.",
        )->toJson());
    }
}
