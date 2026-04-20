<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Laravel\Mcp\Client\Contracts\ClientTransport;
use Laravel\Mcp\Client\Exceptions\ClientException;
use Laravel\Mcp\Transport\JsonRpcNotification;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ClientContext
{
    protected int $requestId = 0;

    /**
     * @param  array<string, mixed>  $capabilities
     */
    public function __construct(
        protected ClientTransport $transport,
        public string $clientName,
        public string $protocolVersion = '2025-11-25',
        public array $capabilities = [],
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function sendRequest(string $method, array $params = []): array
    {
        $request = new JsonRpcRequest(
            id: ++$this->requestId,
            method: $method,
            params: $params,
        );

        $responseJson = $this->transport->send($request->toJson());

        $response = JsonRpcResponse::fromJson($responseJson);

        if (isset($response['error'])) {
            throw new ClientException(
                $response['error']['message'] ?? 'Unknown error',
                (int) ($response['error']['code'] ?? 0),
            );
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function notify(string $method, array $params = []): void
    {
        $notification = new JsonRpcNotification($method, $params);
        $this->transport->notify($notification->toJson());
    }

    public function resetRequestId(): void
    {
        $this->requestId = 0;
    }
}
