<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Mcp\Client\Auth\AuthorizationStrategy;
use Laravel\Mcp\Client\Auth\WwwAuthenticateChallenge;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\SessionExpiredException;
use Psr\Http\Message\StreamInterface;
use Throwable;

class HttpTransport implements Transport
{
    protected ?string $token = null;

    protected ?AuthorizationStrategy $authorizationStrategy = null;

    protected ?string $sessionId = null;

    protected bool $initialized = false;

    protected float $timeoutSeconds = 30.0;

    /** @var array<int, string> */
    protected array $queue = [];

    public function __construct(protected string $url)
    {
        //
    }

    public function url(): string
    {
        return $this->url;
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

    public function withToken(string $token): void
    {
        $this->token = $token;
    }

    public function withAuthorizationStrategy(?AuthorizationStrategy $strategy): void
    {
        $this->authorizationStrategy = $strategy;
    }

    public function send(string $message): void
    {
        $hadSession = $this->sessionId !== null;

        $response = $this->dispatch($message);

        $this->captureSessionId($response);

        if ($this->shouldRetryAfterChallenge($response)) {
            $response->toPsrResponse()->getBody()->close();

            $response = $this->dispatch($message);
            $this->captureSessionId($response);
        }

        if ($response->notFound() && $hadSession) {
            $this->reset();

            throw new SessionExpiredException("Session expired. The server responded with HTTP 404 for endpoint [{$this->url}].");
        }

        if (! $response->successful()) {
            $this->failWith("Unexpected HTTP status [{$response->status()}] from endpoint [{$this->url}].");
        }

        $this->initialized = true;

        if (str_contains($response->header('Content-Type'), 'text/event-stream')) {
            $this->readSseStream($response);

            return;
        }

        $body = trim($response->body());

        if ($response->accepted() || $body === '') {
            return;
        }

        $this->queue[] = $body;
    }

    protected function dispatch(string $message): ClientResponse
    {
        try {
            return Http::withHeaders($this->headers())
                ->withBody($message, 'application/json')
                ->timeout($this->timeoutSeconds)
                ->withOptions(['stream' => true])
                ->post($this->url);
        } catch (ConnectionException $connectionException) {
            $this->failWith("HTTP request to [{$this->url}] failed: {$connectionException->getMessage()}");
        }
    }

    protected function shouldRetryAfterChallenge(ClientResponse $response): bool
    {
        if (! $this->authorizationStrategy instanceof AuthorizationStrategy) {
            return false;
        }

        $status = $response->status();

        if ($status !== 401 && $status !== 403) {
            return false;
        }

        $challenge = WwwAuthenticateChallenge::parse($response->header('WWW-Authenticate'));

        if (! $challenge instanceof WwwAuthenticateChallenge) {
            return false;
        }

        if ($status === 403 && ! $challenge->isInsufficientScope()) {
            return false;
        }

        $token = $this->authorizationStrategy->bearerAfterChallenge($challenge);

        if ($token === null || $token === '') {
            return false;
        }

        $this->token = $token;

        return true;
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

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return $this->buildHeaders($this->resolveBearer());
    }

    /**
     * @return array<string, string>
     */
    protected function terminationHeaders(): array
    {
        return $this->buildHeaders($this->resolveCachedBearer());
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(?string $bearer): array
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
        ];

        if ($this->sessionId !== null) {
            $headers['MCP-Session-Id'] = $this->sessionId;
        }

        if ($this->initialized) {
            $headers['MCP-Protocol-Version'] = ProtocolVersion::LATEST->value;
        }

        if ($bearer !== null && $bearer !== '') {
            $headers['Authorization'] = "Bearer {$bearer}";
        }

        return $headers;
    }

    protected function resolveBearer(): ?string
    {
        $bearer = $this->authorizationStrategy?->bearer();

        return $bearer !== null && $bearer !== '' ? $bearer : $this->token;
    }

    protected function resolveCachedBearer(): ?string
    {
        $bearer = $this->authorizationStrategy?->cachedBearer();

        return $bearer !== null && $bearer !== '' ? $bearer : $this->token;
    }

    protected function captureSessionId(ClientResponse $response): void
    {
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
            Http::withHeaders($this->terminationHeaders())
                ->timeout($this->timeoutSeconds)
                ->delete($this->url);
        } catch (Throwable) {
            //
        }
    }

    protected function reset(): void
    {
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
