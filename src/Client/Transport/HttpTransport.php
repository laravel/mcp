<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\OAuth\WwwAuthenticateChallenge;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\SessionExpiredException;
use Psr\Http\Message\StreamInterface;
use Throwable;

class HttpTransport implements Transport
{
    /** @var string|(Closure(): string)|null */
    protected string|Closure|null $token = null;

    /** @var array<string, string|(Closure(): string)> */
    protected array $headers = [];

    protected ?string $sessionId = null;

    protected bool $initialized = false;

    protected float $timeoutSeconds = 30.0;

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
    public function withToken(string|Closure $token): void
    {
        $this->token = $token;
    }

    /**
     * Add custom headers sent with every request.
     *
     * Reserved protocol headers (Accept, MCP-Session-Id, MCP-Protocol-Version) and
     * the bearer token always take precedence and cannot be overridden, regardless
     * of header-name casing. Values are persisted in the serialized recipe like the
     * bearer token, so treat any secret they carry accordingly.
     *
     * @param  array<string, string|(Closure(): string)>  $headers
     */
    public function withHeaders(array $headers): void
    {
        $this->headers = array_merge($this->headers, $headers);
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
        $headers = [];

        foreach ($this->headers as $key => $value) {
            $headers[$key] = $value instanceof Closure ? (string) $value() : $value;
        }

        return [
            'driver' => 'http',
            'url' => $this->url,
            'token' => $this->token instanceof Closure ? (string) ($this->token)() : $this->token,
            'headers' => $headers,
            'timeoutSeconds' => $this->timeoutSeconds,
        ];
    }

    public function send(string $message): void
    {
        $hadSession = $this->sessionId !== null;

        try {
            $response = Http::withHeaders($this->headers())
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
        $reserved = ['accept', 'mcp-session-id', 'mcp-protocol-version'];

        $token = $this->token instanceof Closure ? (string) ($this->token)() : $this->token;

        if ($token !== null && $token !== '') {
            $reserved[] = 'authorization';
        }

        $headers = [];

        foreach ($this->headers as $key => $value) {
            if (in_array(strtolower($key), $reserved, true)) {
                continue;
            }

            $headers[$key] = $value instanceof Closure ? (string) $value() : $value;
        }

        $headers['Accept'] = 'application/json, text/event-stream';

        if ($this->sessionId !== null) {
            $headers['MCP-Session-Id'] = $this->sessionId;
        }

        if ($this->initialized) {
            $headers['MCP-Protocol-Version'] = ProtocolVersion::LATEST->value;
        }

        if ($token !== null && $token !== '') {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $headers;
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
            Http::withHeaders($this->headers())
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
