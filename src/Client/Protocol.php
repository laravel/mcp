<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Closure;
use Illuminate\Support\Arr;
use JsonException;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Enums\ProtocolEra;
use Laravel\Mcp\Client\Exceptions\UnexpectedResponseException;
use Laravel\Mcp\Client\Methods\Discover;
use Laravel\Mcp\Client\Methods\Initialize;
use Laravel\Mcp\Client\Methods\RetriedRequest;
use Laravel\Mcp\Client\Schema\DiscoverResult;
use Laravel\Mcp\Client\Schema\InitializeResult;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Enums\ResultType;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Exceptions\SessionExpiredException;
use Laravel\Mcp\Schema\Implementation;
use Laravel\Mcp\Support\InputRequests;
use Laravel\Mcp\Transport\JsonRpcNotification;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Throwable;

class Protocol
{
    public const MAX_INPUT_ROUNDS = 5;

    /** @var array<string, Closure(array<string, mixed>): array<string, mixed>> */
    protected array $inputHandlers = [];

    protected bool $connected = false;

    protected bool $connecting = false;

    protected int $nextRequestId = 1;

    protected ?InitializeResult $initializeResult = null;

    protected ?DiscoverResult $discoverResult = null;

    protected ?ProtocolEra $resolvedEra = null;

    public function __construct(
        protected Transport $transport,
        protected Implementation $clientInfo,
        protected ProtocolEra $era = ProtocolEra::AUTO,
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

    public function era(ProtocolEra $era): void
    {
        $this->era = $era;
        $this->resolvedEra = null;

        if ($this->connected) {
            $this->disconnect();
        }
    }

    public function resolvedEra(): ?ProtocolEra
    {
        return $this->resolvedEra;
    }

    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $this->transport->connect();
        $this->connecting = true;

        try {
            $this->resolveEra();

            if ($this->resolvedEra === ProtocolEra::LEGACY) {
                $this->connectLegacy();
            }
        } catch (Throwable $throwable) {
            $this->disconnect();

            throw $throwable;
        } finally {
            $this->connecting = false;
        }

        $this->connected = true;
    }

    protected function connectLegacy(): void
    {
        $this->transport->setProtocolVersion(ProtocolVersion::V2025_11_25->value);

        $this->initializeResult = (new Initialize($this->clientInfo))->handle($this);

        $this->transport->setProtocolVersion($this->initializeResult->protocolVersion);

        $this->notify('notifications/initialized');
    }

    protected function connectModern(): void
    {
        $this->transport->setProtocolVersion(ProtocolVersion::LATEST->value);

        $this->discoverResult = (new Discover)->handle($this);

        if (! in_array(ProtocolVersion::LATEST->value, $this->discoverResult->supportedVersions, true)) {
            throw new ClientException(sprintf(
                'The server does not support protocol version [%s]. It supports [%s].',
                ProtocolVersion::LATEST->value,
                implode(', ', $this->discoverResult->supportedVersions),
            ));
        }
    }

    protected function resolveEra(): void
    {
        if ($this->era === ProtocolEra::LEGACY || $this->resolvedEra === ProtocolEra::LEGACY) {
            $this->resolvedEra = ProtocolEra::LEGACY;

            return;
        }

        if ($this->resolvedEra === ProtocolEra::MODERN) {
            $this->connectModern();

            return;
        }

        $this->resolvedEra = ProtocolEra::MODERN;

        try {
            $this->connectModern();

            return;
        } catch (JsonRpcException $jsonRpcException) {
            if ($this->era === ProtocolEra::MODERN || $this->speaksModern($jsonRpcException)) {
                throw $jsonRpcException;
            }
        } catch (UnexpectedResponseException $unexpectedResponseException) {
            if ($this->era === ProtocolEra::MODERN) {
                throw $unexpectedResponseException;
            }
        }

        $this->resolvedEra = ProtocolEra::LEGACY;
    }

    protected function speaksModern(JsonRpcException $jsonRpcException): bool
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
            $result = $this->attempt($method);
        } catch (SessionExpiredException) {
            $this->connect();

            $result = $this->attempt($method);
        }

        return $this->fulfillInputRequests($method, $result);
    }

    /**
     * @param  Method<mixed>  $method
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function fulfillInputRequests(Method $method, array $result, int $round = 0): array
    {
        if (($result['resultType'] ?? null) !== ResultType::INPUT_REQUIRED->value) {
            return $result;
        }

        if ($round >= self::MAX_INPUT_ROUNDS) {
            throw new ClientException('The server asked for input more than '.self::MAX_INPUT_ROUNDS.' times for the same request.');
        }

        $inputRequests = is_array($result['inputRequests'] ?? null) ? $result['inputRequests'] : [];
        $inputResponses = [];

        foreach ($inputRequests as $key => $inputRequest) {
            $inputResponses[$key] = $this->fulfill(is_array($inputRequest) ? $inputRequest : []);
        }

        $retry = new RetriedRequest(
            $method,
            $inputResponses,
            is_string($result['requestState'] ?? null) ? $result['requestState'] : null,
        );

        return $this->fulfillInputRequests($method, $this->attempt($retry), $round + 1);
    }

    /**
     * @param  array<string, mixed>  $inputRequest
     * @return array<string, mixed>
     */
    protected function fulfill(array $inputRequest): array
    {
        $method = $inputRequest['method'] ?? null;
        $handler = is_string($method) ? ($this->inputHandlers[$method] ?? null) : null;

        if (! $handler instanceof Closure) {
            throw new ClientException("The server requested [{$method}], which this client has no handler for.");
        }

        $response = $handler(is_array($inputRequest['params'] ?? null) ? $inputRequest['params'] : []);

        if (! is_array($response)) {
            throw new ClientException("The handler for [{$method}] must return an array.");
        }

        return $response;
    }

    /**
     * @param  Closure(array<string, mixed>): array<string, mixed>  $handler
     */
    public function onInput(string $method, Closure $handler): void
    {
        $this->inputHandlers[$method] = $handler;
    }

    /**
     * @return array<string, object>
     */
    protected function clientCapabilities(): array
    {
        $capabilities = [];

        foreach (array_keys($this->inputHandlers) as $method) {
            if (isset(InputRequests::CAPABILITIES[$method])) {
                $capabilities[InputRequests::CAPABILITIES[$method]] = (object) [];
            }
        }

        return $capabilities;
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
    protected function params(Method $method): array
    {
        $params = $method->params();

        if ($this->resolvedEra !== ProtocolEra::MODERN) {
            return $params;
        }

        $params['_meta'] = [
            MetaKey::PROTOCOL_VERSION->value => ProtocolVersion::LATEST->value,
            MetaKey::CLIENT_CAPABILITIES->value => (object) $this->clientCapabilities(),
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
