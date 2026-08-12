<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Illuminate\Support\Arr;
use JsonException;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Contracts\UsesProtocol;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\Exceptions\TransportException;
use Laravel\Mcp\Client\Methods\Discover;
use Laravel\Mcp\Client\Methods\Initialize;
use Laravel\Mcp\Client\Schema\DiscoverResult;
use Laravel\Mcp\Client\Schema\InitializeResult;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Enums\ProtocolHandshake;
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

    protected ?NegotiatedConnection $connection = null;

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
        return $this->connection?->initializeResult();
    }

    public function discoverResult(): ?DiscoverResult
    {
        return $this->connection?->discoverResult();
    }

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        return $this->connection?->capabilities() ?? [];
    }

    public function serverInfo(): ?Implementation
    {
        return $this->connection?->serverInfo();
    }

    public function instructions(): ?string
    {
        return $this->connection?->instructions();
    }

    public function pinProtocolVersion(?ProtocolVersion $protocolVersion): void
    {
        $this->protocolVersion = $protocolVersion;
        $this->connection = null;

        if ($this->connected) {
            $this->disconnect();
        }
    }

    public function resolvedProtocolVersion(): ?ProtocolVersion
    {
        return $this->connection?->protocolVersion;
    }

    public function pinnedProtocolVersion(): ?ProtocolVersion
    {
        return $this->protocolVersion;
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
        $pinned = $this->protocolVersion;

        if ($pinned instanceof ProtocolVersion) {
            $this->connection = $pinned->handshake() === ProtocolHandshake::Discovery
                ? $this->discover($pinned)
                : $this->initialize($pinned, true);

            return;
        }

        $remembered = $this->connection?->protocolVersion;

        if ($remembered?->handshake() === ProtocolHandshake::Initialize) {
            try {
                $this->connection = $this->initialize($remembered);

                return;
            } catch (OAuthException $oAuthException) {
                throw $oAuthException;
            } catch (Throwable) {
                $this->connection = null;

                $this->transport->connect();
            }
        }

        $this->connection = $this->probe();
    }

    protected function probe(): NegotiatedConnection
    {
        try {
            return $this->discover();
        } catch (JsonRpcException $jsonRpcException) {
            if ($this->identifiesModernServer($jsonRpcException)) {
                return $this->retryWithMutualVersion($jsonRpcException);
            }

            if (! $this->identifiesLegacyServer($jsonRpcException)) {
                throw $jsonRpcException;
            }

            $rejection = null;
        } catch (TransportException $transportException) {
            $rejection = $transportException;
        }

        try {
            $this->transport->connect();

            return $this->initialize(ProtocolVersion::V2025_11_25);
        } catch (OAuthException $oAuthException) {
            throw $oAuthException;
        } catch (Throwable $throwable) {
            throw $rejection instanceof ClientException
                ? new ClientException(sprintf(
                    '%s The legacy handshake also failed: %s',
                    $rejection->getMessage(),
                    $throwable->getMessage(),
                ), 0, $throwable)
                : $throwable;
        }
    }

    protected function initialize(ProtocolVersion $protocolVersion, bool $pinned = false): NegotiatedConnection
    {
        $result = InitializeResult::from($this->attempt(
            new Initialize($this->clientInfo, $protocolVersion),
            $protocolVersion,
        ));
        $settled = ProtocolVersion::from($result->protocolVersion);

        if ($pinned && $settled !== $protocolVersion) {
            throw $this->versionMismatch($settled, $protocolVersion);
        }

        $this->notify('notifications/initialized', $settled);

        return new NegotiatedConnection($settled, $result);
    }

    protected function discover(?ProtocolVersion $pinned = null): NegotiatedConnection
    {
        $offered = $pinned ?? ProtocolVersion::LATEST;
        $result = DiscoverResult::from($this->attempt(new Discover, $offered));
        $settled = ProtocolVersion::preferredFrom(...$result->supportedVersions);

        if (! $settled instanceof ProtocolVersion) {
            throw new ClientException(sprintf(
                'The server supports protocol versions [%s]. This client supports [%s].',
                implode(', ', $result->supportedVersions),
                implode(', ', ProtocolVersion::clientSupported()),
            ));
        }

        if ($pinned instanceof ProtocolVersion && $settled !== $pinned) {
            throw $this->versionMismatch($settled, $pinned);
        }

        return $settled->handshake() === ProtocolHandshake::Initialize
            ? $this->initialize($settled)
            : new NegotiatedConnection($settled, $result);
    }

    protected function retryWithMutualVersion(JsonRpcException $jsonRpcException): NegotiatedConnection
    {
        $supported = Arr::get($jsonRpcException->data() ?? [], 'supported');

        $protocolVersion = is_array($supported)
            ? ProtocolVersion::preferredFrom(...array_values(array_filter($supported, is_string(...))))
            : null;

        if (! $protocolVersion instanceof ProtocolVersion || $protocolVersion->handshake() !== ProtocolHandshake::Initialize) {
            throw $jsonRpcException;
        }

        return $this->initialize($protocolVersion);
    }

    protected function versionMismatch(ProtocolVersion $settled, ProtocolVersion $pinned): ClientException
    {
        return new ClientException(sprintf(
            'The server settled on protocol version [%s] while [%s] was requested.',
            $settled->value,
            $pinned->value,
        ));
    }

    protected function identifiesModernServer(JsonRpcException $jsonRpcException): bool
    {
        return in_array($jsonRpcException->getCode(), [
            ErrorCode::HEADER_MISMATCH->value,
            ErrorCode::MISSING_REQUIRED_CLIENT_CAPABILITY->value,
            ErrorCode::UNSUPPORTED_PROTOCOL_VERSION->value,
        ], true);
    }

    protected function identifiesLegacyServer(JsonRpcException $jsonRpcException): bool
    {
        return in_array($jsonRpcException->getCode(), [
            ErrorCode::PARSE_ERROR->value,
            ErrorCode::INVALID_REQUEST->value,
            ErrorCode::METHOD_NOT_FOUND->value,
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
            return $this->attempt($method, $this->connectionProtocol());
        } catch (SessionExpiredException) {
            $this->connect();

            return $this->attempt($method, $this->connectionProtocol());
        }
    }

    /**
     * @param  Method<mixed>  $method
     * @return array<string, mixed>
     */
    protected function attempt(Method $method, ProtocolVersion $protocolVersion): array
    {
        $this->configureTransport($protocolVersion);

        $request = new JsonRpcRequest(
            id: $this->nextRequestId++,
            method: $method->method(),
            params: $this->params($method, $protocolVersion),
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
    protected function params(Method $method, ProtocolVersion $protocolVersion): array
    {
        $params = $method->params();

        if ($protocolVersion->handshake() !== ProtocolHandshake::Discovery) {
            return $params;
        }

        $params['_meta'] = [
            MetaKey::PROTOCOL_VERSION->value => $protocolVersion->value,
            MetaKey::CLIENT_CAPABILITIES->value => (object) [],
            MetaKey::CLIENT_INFO->value => $this->clientInfo->toArray(),
            ...Arr::wrap(Arr::get($params, '_meta')),
        ];

        return $params;
    }

    public function notify(string $method, ?ProtocolVersion $protocolVersion = null): void
    {
        $this->configureTransport($protocolVersion ?? $this->connectionProtocol());

        $notification = new JsonRpcNotification($method, []);

        $this->transport->send($notification->toJson());
    }

    protected function connectionProtocol(): ProtocolVersion
    {
        if (! $this->connection instanceof NegotiatedConnection) {
            throw new ClientException('The client has not negotiated a protocol version.');
        }

        return $this->connection->protocolVersion;
    }

    protected function configureTransport(ProtocolVersion $protocolVersion): void
    {
        if ($this->transport instanceof UsesProtocol) {
            $this->transport->useProtocol($protocolVersion);
        }
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
