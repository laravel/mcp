<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\SessionExpiredException;
use Psr\Http\Message\StreamInterface;
use Throwable;

class HttpTransport implements Transport
{
    protected ?string $token = null;

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

    public function withToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @return array<string, mixed>
     */
    public function recipe(): array
    {
        return [
            'driver' => 'http',
            'url' => $this->url,
            'token' => $this->token,
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
        $headers = [
            'Accept' => 'application/json, text/event-stream',
        ];

        if ($this->sessionId !== null) {
            $headers['MCP-Session-Id'] = $this->sessionId;
        }

        if ($this->initialized) {
            $headers['MCP-Protocol-Version'] = ProtocolVersion::LATEST->value;
        }

        if ($this->token !== null) {
            $headers['Authorization'] = "Bearer {$this->token}";
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
