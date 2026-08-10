<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Illuminate\Support\Arr;
use JsonException;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Contracts\MirrorsParameters;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Exceptions\RequestRejectedException;
use Laravel\Mcp\Client\Methods\Discover;
use Laravel\Mcp\Client\Methods\Initialize;
use Laravel\Mcp\Client\Schema\DiscoverResult;
use Laravel\Mcp\Client\Schema\InitializeResult;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Exceptions\SessionExpiredException;
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

    protected ?DiscoverResult $discoverResult = null;

    protected ?ProtocolVersion $resolvedProtocolVersion = null;

    public function __construct(
        protected Transport $transport,
        protected Implementation $clientInfo,
        protected ?ProtocolVersion $protocolVersion = null,
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

    public function discoverResult(): ?DiscoverResult
    {
        return $this->discoverResult;
    }

    public function protocolVersion(?ProtocolVersion $protocolVersion): void
    {
        $this->protocolVersion = $protocolVersion;
        $this->resolvedProtocolVersion = null;

        if ($this->connected) {
            $this->disconnect();
        }
    }

    public function resolvedProtocolVersion(): ?ProtocolVersion
    {
        return $this->resolvedProtocolVersion;
    }

    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $this->transport->connect();
        $this->connecting = true;

        try {
            $this->handshake();
        } catch (Throwable $throwable) {
            $this->disconnect();

            throw $throwable;
        } finally {
            $this->connecting = false;
        }

        $this->connected = true;
    }

    protected function handshake(): void
    {
        $version = $this->protocolVersion ?? $this->resolvedProtocolVersion;

        if ($version instanceof ProtocolVersion) {
            $version->usesDiscovery()
                ? $this->discover()
                : $this->initialize($version);

            return;
        }

        try {
            $this->discover();

            return;
        } catch (JsonRpcException $jsonRpcException) {
            if ($this->identifiesModernServer($jsonRpcException)) {
                $this->retryWithMutualVersion($jsonRpcException);

                return;
            }

            $rejection = null;
        } catch (RequestRejectedException $requestRejectedException) {
            $rejection = $requestRejectedException;
        }

        try {
            $this->initialize(ProtocolVersion::V2025_11_25);
        } catch (Throwable $throwable) {
            throw $rejection instanceof RequestRejectedException
                ? new ClientException(sprintf(
                    '%s The legacy handshake also failed: %s',
                    $rejection->getMessage(),
                    $throwable->getMessage(),
                ), 0, $throwable)
                : $throwable;
        }
    }

    protected function initialize(ProtocolVersion $version): void
    {
        $this->resolvedProtocolVersion = null;

        $this->transport->setProtocolVersion($version->value);

        $this->initializeResult = (new Initialize($this->clientInfo, $version))->handle($this);

        $this->resolvedProtocolVersion = ProtocolVersion::from($this->initializeResult->protocolVersion);

        $this->transport->setProtocolVersion($this->initializeResult->protocolVersion);

        $this->notify('notifications/initialized');
    }

    protected function discover(): void
    {
        $this->resolvedProtocolVersion = ProtocolVersion::LATEST;

        $this->transport->setProtocolVersion(ProtocolVersion::LATEST->value);

        try {
            $this->discoverResult = (new Discover)->handle($this);

            $version = ProtocolVersion::mutual($this->discoverResult->supportedVersions);

            if (! $version instanceof ProtocolVersion) {
                throw new ClientException(sprintf(
                    'The server supports protocol versions [%s]. This client supports [%s].',
                    implode(', ', $this->discoverResult->supportedVersions),
                    implode(', ', ProtocolVersion::negotiable()),
                ));
            }
        } catch (Throwable $throwable) {
            $this->resolvedProtocolVersion = null;

            throw $throwable;
        }

        if (! $version->usesDiscovery()) {
            $this->initialize($version);

            return;
        }

        $this->resolvedProtocolVersion = $version;

        $this->transport->setProtocolVersion($version->value);
    }

    protected function retryWithMutualVersion(JsonRpcException $jsonRpcException): void
    {
        $supported = $jsonRpcException->data()['supported'] ?? null;

        $version = is_array($supported)
            ? ProtocolVersion::mutual(array_values(array_filter($supported, is_string(...))))
            : null;

        if (! $version instanceof ProtocolVersion || $version->usesDiscovery()) {
            throw $jsonRpcException;
        }

        $this->initialize($version);
    }

    protected function identifiesModernServer(JsonRpcException $jsonRpcException): bool
    {
        return in_array($jsonRpcException->getCode(), [
            ErrorCode::HEADER_MISMATCH->value,
            ErrorCode::MISSING_REQUIRED_CLIENT_CAPABILITY->value,
            ErrorCode::UNSUPPORTED_PROTOCOL_VERSION->value,
        ], true);
    }

    public function disconnect(): void
    {
        $this->connected = false;

        $this->transport->disconnect();
    }

    /**
     * @param  Method<mixed>  $method
     * @return array<string, mixed>
     */
    public function dispatch(Method $method): array
    {
        if (! $this->connected && ! $this->connecting) {
            $this->connect();
        }

        try {
            return $this->attempt($method);
        } catch (SessionExpiredException) {
            $this->connect();

            return $this->attempt($method);
        }
    }

    /**
     * @param  Method<mixed>  $method
     * @return array<string, mixed>
     */
    protected function attempt(Method $method): array
    {
        $request = new JsonRpcRequest(
            id: $this->nextRequestId++,
            method: $method->method(),
            params: $this->params($method),
        );

        try {
            $this->transport->send(
                $request->toJson(),
                $method instanceof MirrorsParameters ? $method->requestHeaders() : [],
            );

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

                if (! is_array($response) || Arr::get($response, 'jsonrpc') !== '2.0') {
                    throw new ClientException('Invalid JSON-RPC response from server.');
                }

                $this->handleServerRequest($response);
            } while (Arr::get($response, 'id') !== $request->id);

            $hasResult = Arr::has($response, 'result');
            $hasError = Arr::has($response, 'error');
            $error = Arr::get($response, 'error');

            if ($hasResult === $hasError) {
                throw new ClientException('Invalid JSON-RPC response: must contain exactly one of "result" or "error".');
            }

            if ($hasError && ! is_array($error)) {
                throw new ClientException('Invalid JSON-RPC error payload.');
            }
        } catch (Throwable $throwable) {
            if ($this->connected) {
                $this->disconnect();
            }

            throw $throwable;
        }

        if ($hasError) {
            $message = Arr::get($error, 'message', 'Unknown JSON-RPC error.');
            $code = Arr::get($error, 'code', 0);
            $data = Arr::get($error, 'data');

            throw new JsonRpcException(
                is_string($message) ? $message : 'Unknown JSON-RPC error.',
                is_int($code) ? $code : 0,
                Arr::get($response, 'id'),
                is_array($data) ? $data : null,
            );
        }

        $result = Arr::get($response, 'result');

        return is_array($result) ? $result : [];
    }

    /**
     * @param  Method<mixed>  $method
     * @return array<string, mixed>
     */
    protected function params(Method $method): array
    {
        $params = $method->params();

        if (! $this->resolvedProtocolVersion?->usesDiscovery()) {
            return $params;
        }

        $params['_meta'] = [
            MetaKey::PROTOCOL_VERSION->value => ProtocolVersion::LATEST->value,
            MetaKey::CLIENT_CAPABILITIES->value => (object) [],
            MetaKey::CLIENT_INFO->value => $this->clientInfo->toArray(),
            ...is_array($params['_meta'] ?? null) ? $params['_meta'] : [],
        ];

        return $params;
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
        $id = Arr::get($frame, 'id');
        $method = Arr::get($frame, 'method');

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
