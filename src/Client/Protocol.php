<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Illuminate\Support\Arr;
use JsonException;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\Methods\Discover;
use Laravel\Mcp\Client\Methods\Initialize;
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

    protected bool $modern = false;

    /**
     * Whether the server is known to require the legacy initialize handshake.
     * The era determination is a property of the server, so it is cached for
     * the lifetime of this protocol instance and reconnects skip the probe.
     */
    protected bool $knownLegacy = false;

    protected ?string $negotiatedVersion = null;

    protected int $nextRequestId = 1;

    protected int $nextProbeId = 1;

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
            $this->initializeResult = $this->negotiate();
        } catch (Throwable $throwable) {
            $this->disconnect();

            throw $throwable;
        } finally {
            $this->connecting = false;
        }

        $this->connected = true;
    }

    /**
     * Probe the server with a modern, stateless [server/discover] request and
     * fall back to the legacy initialize handshake when the server does not
     * speak a mutually supported per-request-metadata revision.
     */
    protected function negotiate(): InitializeResult
    {
        if ($this->knownLegacy) {
            return $this->legacyHandshake();
        }

        try {
            return $this->discover(ProtocolVersion::LATEST->value);
        } catch (JsonRpcException $jsonRpcException) {
            if ($jsonRpcException->getCode() === ErrorCode::UNSUPPORTED_PROTOCOL_VERSION->value) {
                $mutualVersion = $this->selectModernVersion(
                    Arr::wrap($jsonRpcException->data()['supported'] ?? []),
                );

                if ($mutualVersion !== null) {
                    return $this->discover($mutualVersion);
                }
            }

            // Any other JSON-RPC error (e.g. method not found) identifies a legacy server.
        } catch (OAuthException $oauthException) {
            throw $oauthException;
        } catch (ClientException) {
            // An HTTP-level rejection without a modern JSON-RPC error body, or a
            // stdio failure, identifies a legacy server.
        }

        // The transport may have torn itself down while the probe failed, and any
        // probe-related transport state must not leak into the legacy handshake.
        $this->transport->connect();

        return $this->legacyHandshake();
    }

    /**
     * @throws ClientException
     * @throws JsonRpcException
     */
    protected function discover(string $protocolVersion): InitializeResult
    {
        $result = (new Discover($this->clientInfo, $protocolVersion))->handle($this);

        $negotiated = in_array($protocolVersion, $result->supportedVersions, true)
            ? $protocolVersion
            : $this->selectModernVersion($result->supportedVersions);

        if ($negotiated === null) {
            throw new JsonRpcException('Unsupported protocol version', ErrorCode::UNSUPPORTED_PROTOCOL_VERSION->value, null, [
                'supported' => $result->supportedVersions,
                'requested' => $protocolVersion,
            ]);
        }

        $this->modern = true;
        $this->negotiatedVersion = $negotiated;
        $this->transport->setProtocolVersion($negotiated);

        return $result->toInitializeResult($negotiated);
    }

    protected function legacyHandshake(): InitializeResult
    {
        $this->knownLegacy = true;

        $result = (new Initialize($this->clientInfo))->handle($this);

        $this->modern = false;
        $this->negotiatedVersion = $result->protocolVersion;
        $this->transport->setProtocolVersion($result->protocolVersion);

        $this->notify('notifications/initialized');

        return $result;
    }

    /**
     * The newest modern revision both parties support, if any.
     *
     * @param  array<array-key, mixed>  $serverVersions
     */
    protected function selectModernVersion(array $serverVersions): ?string
    {
        $mutual = array_intersect(
            array_filter(ProtocolVersion::clientSupported(), ProtocolVersion::isModern(...)),
            array_filter($serverVersions, is_string(...)),
        );

        return $mutual === [] ? null : max($mutual);
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
        $params = $method->params();

        if ($this->modern) {
            $params = $this->withModernMeta($params);
        }

        $request = new JsonRpcRequest(
            id: $method instanceof Discover ? 'discover-'.$this->nextProbeId++ : $this->nextRequestId++,
            method: $method->method(),
            params: $params,
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
        $result = is_array($result) ? $result : [];

        // Clients must treat results that omit [resultType] as complete. Interim
        // multi-round-trip results cannot occur, as this client does not advertise
        // any of the capabilities that allow servers to request input.
        if (($result['resultType'] ?? 'complete') === 'input_required') {
            throw new ClientException('The server returned an [input_required] result, but this client does not support multi round-trip requests.');
        }

        return $result;
    }

    /**
     * Stamp the protocol metadata that modern revisions carry on every request.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function withModernMeta(array $params): array
    {
        $params['_meta'] = array_merge([
            MetaKey::PROTOCOL_VERSION->value => $this->negotiatedVersion ?? ProtocolVersion::LATEST->value,
            MetaKey::CLIENT_INFO->value => $this->clientInfo->toArray(),
            MetaKey::CLIENT_CAPABILITIES->value => (object) [],
        ], is_array($params['_meta'] ?? null) ? $params['_meta'] : []);

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
