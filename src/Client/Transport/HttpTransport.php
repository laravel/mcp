<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
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
        $this->initialized = false;
        $this->queue = [];
    }

    public function disconnect(): void
    {
        $this->terminateSession();

        $this->sessionId = null;
        $this->initialized = false;
        $this->queue = [];
    }

    public function setTimeoutSeconds(float $seconds): void
    {
        $this->timeoutSeconds = $seconds;
    }

    public function withToken(string $token): void
    {
        $this->token = $token;
    }

    public function send(string $message): void
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->withBody($message, 'application/json')
                ->timeout($this->timeoutSeconds)
                ->post($this->url);
        } catch (ConnectionException $connectionException) {
            $this->failWith("HTTP request to [{$this->url}] failed: {$connectionException->getMessage()}");
        }

        $this->captureSessionId($response);

        $status = $response->status();

        if ($status === 404) {
            $this->failWith("Session expired. The server responded with HTTP 404 for endpoint [{$this->url}].");
        }

        if ($status < 200 || $status >= 300) {
            $this->failWith("Unexpected HTTP status [{$status}] from endpoint [{$this->url}].");
        }

        $this->initialized = true;

        $body = $response->body();

        if ($status === 202 || trim($body) === '') {
            return;
        }

        foreach ($this->parseMessages($response->header('Content-Type'), $body) as $frame) {
            $this->queue[] = $frame;
        }
    }

    public function receive(): string
    {
        $message = array_shift($this->queue);

        if ($message === null) {
            throw new ClientException('No message available from the HTTP transport.');
        }

        return $message;
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

    protected function captureSessionId(Response $response): void
    {
        $sessionId = $response->header('MCP-Session-Id');

        if ($sessionId !== '') {
            $this->sessionId = $sessionId;
        }
    }

    /**
     * @return array<int, string>
     */
    protected function parseMessages(string $contentType, string $body): array
    {
        if (str_contains($contentType, 'text/event-stream')) {
            return $this->parseSse($body);
        }

        return [trim($body)];
    }

    /**
     * @return array<int, string>
     */
    protected function parseSse(string $body): array
    {
        $messages = [];

        foreach (explode("\n\n", str_replace("\r\n", "\n", $body)) as $event) {
            $data = $this->extractEventData($event);

            if ($data !== '') {
                $messages[] = $data;
            }
        }

        return $messages;
    }

    protected function extractEventData(string $event): string
    {
        $data = [];

        foreach (explode("\n", $event) as $line) {
            if (str_starts_with($line, 'data:')) {
                $data[] = ltrim(substr($line, 5), ' ');
            }
        }

        return implode("\n", $data);
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

    protected function failWith(string $message): never
    {
        $this->sessionId = null;
        $this->initialized = false;
        $this->queue = [];

        throw new ClientException($message);
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
