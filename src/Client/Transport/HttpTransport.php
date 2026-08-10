<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Contracts\UsesProtocol;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\Exceptions\TransportException;
use Laravel\Mcp\Client\OAuth\WwwAuthenticateChallenge;
use Laravel\Mcp\Enums\ProtocolHandshake;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Enums\RequestHeader;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\SessionExpiredException;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Psr\Http\Message\StreamInterface;
use SensitiveParameter;
use Throwable;

class HttpTransport implements Transport, UsesProtocol
{
    /** @var string|(Closure(): string)|null */
    protected string|Closure|null $token = null;

    protected ?ProtocolVersion $protocolVersion = null;

    protected ?string $sessionId = null;

    protected bool $initialized = false;

    protected float $timeoutSeconds = 30.0;

    /** @var array<string, string> */
    protected array $customHeaders = [];

    /** @var array<int, string> */
    protected array $queue = [];

    public function __construct(protected string $url)
    {
        //
    }

    public function connect(): void
    {
        $this->reset();
    }

    public function disconnect(): void
    {
        $this->terminateSession();

        $this->reset();
    }

    public function setTimeoutSeconds(float $seconds): void
    {
        $this->timeoutSeconds = $seconds;
    }

    /**
     * @param  string|Closure(): string  $token
     */
    public function withToken(#[SensitiveParameter] string|Closure $token): void
    {
        $this->token = $token;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): void
    {
        $this->customHeaders = array_merge($this->customHeaders, $headers);
    }

    public function url(): string
    {
        return $this->url;
    }

    /**
     * @return array<string, mixed>
     */
    public function recipe(): array
    {
        return [
            'driver' => 'http',
            'url' => $this->url,
            'token' => $this->token instanceof Closure ? (string) ($this->token)() : $this->token,
            'headers' => $this->customHeaders,
            'timeoutSeconds' => $this->timeoutSeconds,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function send(string $message, array $headers = []): void
    {
        $hadSession = $this->sessionId !== null;

        try {
            $response = Http::withHeaders($this->headers($message, $headers))
                ->withBody($message, 'application/json')
                ->timeout($this->timeoutSeconds)
                ->withOptions(['stream' => true])
                ->post($this->url);
        } catch (ConnectionException $connectionException) {
            $this->failWith("HTTP request to [{$this->url}] failed: {$connectionException->getMessage()}");
        }

        $this->captureSessionId($response);

        if ($response->status() === 401 || $response->status() === 403) {
            $challenge = WwwAuthenticateChallenge::parse($response->header('WWW-Authenticate'));

            $this->reset();

            throw new AuthorizationRequiredException(
                "The server responded with HTTP {$response->status()} for endpoint [{$this->url}]. Authorization is required.",
                $challenge,
            );
        }

        if ($response->notFound() && $hadSession) {
            $this->reset();

            throw new SessionExpiredException("Session expired. The server responded with HTTP 404 for endpoint [{$this->url}].");
        }

        if (! $response->successful()) {
            $content = trim($response->body());

            if ($this->hasJsonRpcError($content)) {
                $this->queue[] = $content;

                return;
            }

            if ($response->notFound() || ($response->serverError() && $response->status() !== 501)) {
                $this->failWith("Unexpected HTTP status [{$response->status()}] from endpoint [{$this->url}].");
            }

            $this->reset();

            throw new TransportException("The endpoint [{$this->url}] rejected the request with HTTP status [{$response->status()}].");
        }

        if ($this->protocolVersion?->handshake() === ProtocolHandshake::Initialize) {
            $this->initialized = true;
        }

        if (str_contains($response->header('Content-Type'), 'text/event-stream')) {
            $this->readSseStream($response);

            return;
        }

        $content = trim($response->body());

        if ($response->accepted() || $content === '') {
            return;
        }

        $this->queue[] = $content;
    }

    public function useProtocol(ProtocolVersion $protocolVersion): void
    {
        $previous = $this->protocolVersion;

        if ($previous instanceof ProtocolVersion && $previous->handshake() !== $protocolVersion->handshake()) {
            $this->sessionId = null;
            $this->initialized = false;
        }

        $this->protocolVersion = $protocolVersion;
    }

    public function receive(): string
    {
        $message = array_shift($this->queue);

        if ($message === null) {
            throw new ClientException('No message available from the HTTP transport.');
        }

        return $message;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    protected function hasJsonRpcError(string $content): bool
    {
        $body = json_decode($content, true);

        return is_array($body) && Arr::get($body, 'jsonrpc') === '2.0' && is_array(Arr::get($body, 'error'));
    }

    /**
     * @param  array<string, string>  $mirrored
     * @return array<string, string>
     */
    protected function headers(string $message = '', array $mirrored = []): array
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            ...$this->eraHeaders(),
        ];

        if ($this->protocolVersion?->handshake() === ProtocolHandshake::Discovery) {
            $headers = array_merge($headers, $this->mirroredHeaders($message), $mirrored);
        }

        $token = $this->token instanceof Closure ? (string) ($this->token)() : $this->token;

        if ($token !== null && $token !== '') {
            $headers['Authorization'] = "Bearer {$token}";
        }

        foreach ($this->customHeaders as $name => $value) {
            foreach (array_keys($headers) as $existing) {
                if (strcasecmp($existing, $name) === 0) {
                    unset($headers[$existing]);
                }
            }

            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    protected function mirroredHeaders(string $message): array
    {
        $body = json_decode($message, true);

        if (! is_array($body) || ! isset($body['id']) || ! is_string(Arr::get($body, 'method'))) {
            return [];
        }

        $id = Arr::get($body, 'id');
        $params = Arr::get($body, 'params');

        return (new JsonRpcRequest(
            id: is_int($id) || is_string($id) ? $id : 0,
            method: Arr::get($body, 'method'),
            params: is_array($params) ? $params : [],
        ))->mirroredHeaders();
    }

    /**
     * @return array<string, string>
     */
    protected function eraHeaders(): array
    {
        $version = $this->protocolVersion;

        if ($version?->handshake() === ProtocolHandshake::Discovery) {
            return [RequestHeader::PROTOCOL_VERSION->value => $version->value];
        }

        $headers = [];

        if ($this->sessionId !== null) {
            $headers['MCP-Session-Id'] = $this->sessionId;
        }

        if ($this->initialized && $version instanceof ProtocolVersion) {
            $headers[RequestHeader::PROTOCOL_VERSION->value] = $version->value;
        }

        return $headers;
    }

    protected function captureSessionId(ClientResponse $response): void
    {
        if ($this->protocolVersion?->handshake() !== ProtocolHandshake::Initialize) {
            return;
        }

        $sessionId = $response->header('MCP-Session-Id');

        if ($sessionId !== '') {
            $this->sessionId = $sessionId;
        }
    }

    protected function readSseStream(ClientResponse $response): void
    {
        $stream = $response->toPsrResponse()->getBody();

        while (! $stream->eof()) {
            $line = trim($this->readLine($stream));

            if (Str::startsWith($line, 'data:')) {
                $this->queueSseEvent(trim(Str::after($line, 'data:')));
            }
        }
    }

    protected function readLine(StreamInterface $stream): string
    {
        $line = '';

        while (! $stream->eof()) {
            $byte = $stream->read(1);

            if ($byte === '') {
                break;
            }

            $line .= $byte;

            if ($byte === "\n") {
                break;
            }
        }

        return $line;
    }

    protected function queueSseEvent(string $data): void
    {
        if ($data === '') {
            return;
        }

        $decoded = json_decode($data, true);

        if (is_array($decoded) && isset($decoded['method'], $decoded['id'])) {
            $this->failWith('The server initiated a request over the SSE stream, which this HTTP client does not support.');
        }

        $this->queue[] = $data;
    }

    protected function terminateSession(): void
    {
        if ($this->sessionId === null) {
            return;
        }

        try {
            Http::withHeaders($this->headers())
                ->timeout($this->timeoutSeconds)
                ->delete($this->url);
        } catch (Throwable) {
            //
        }
    }

    protected function reset(): void
    {
        $this->protocolVersion = null;
        $this->sessionId = null;
        $this->initialized = false;
        $this->queue = [];
    }

    protected function failWith(string $message): never
    {
        $this->reset();

        throw new ClientException($message);
    }
}
