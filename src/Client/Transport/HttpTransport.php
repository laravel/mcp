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
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\SessionExpiredException;
use Psr\Http\Message\StreamInterface;
use SensitiveParameter;
use Throwable;

class HttpTransport implements Transport
{
    /** @var string|(Closure(): string)|null */
    protected string|Closure|null $token = null;

    protected ?string $sessionId = null;

    protected bool $initialized = false;

    protected ?string $protocolVersion = null;

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

    public function send(string $message): void
    {
        $hadSession = $this->sessionId !== null;

        try {
            $response = Http::withHeaders(array_merge($this->headers(), $this->requestMetadataHeaders($message)))
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
            // Modern servers reject requests with an HTTP error status carrying a
            // JSON-RPC error body (e.g. 400 UnsupportedProtocolVersion, 404 Method
            // not found). Surface those to the protocol layer instead of failing.
            $body = trim($response->body());
            $decoded = json_decode($body, true);

            if (is_array($decoded) && isset($decoded['error']['code'])) {
                $this->queue[] = $body;

                return;
            }

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

    public function setProtocolVersion(string $version): void
    {
        $this->protocolVersion = $version;
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
            $headers['MCP-Protocol-Version'] = $this->protocolVersion ?? ProtocolVersion::LATEST_LEGACY->value;
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
     * The request metadata headers modern revisions require on every POST:
     * MCP-Protocol-Version, Mcp-Method, and Mcp-Name mirror the body fields.
     *
     * @return array<string, string>
     */
    protected function requestMetadataHeaders(string $message): array
    {
        $decoded = json_decode($message, true);

        if (! is_array($decoded) || ! array_key_exists('id', $decoded) || ! is_string($decoded['method'] ?? null)) {
            return [];
        }

        $meta = $decoded['params']['_meta'] ?? null;
        $version = is_array($meta) ? ($meta[MetaKey::PROTOCOL_VERSION->value] ?? null) : null;

        if (! is_string($version) || ! ProtocolVersion::isModern($version)) {
            return [];
        }

        $headers = [
            'MCP-Protocol-Version' => $version,
            'Mcp-Method' => $decoded['method'],
        ];

        $nameParameter = match ($decoded['method']) {
            'tools/call', 'prompts/get' => 'name',
            'resources/read' => 'uri',
            default => null,
        };

        $name = $nameParameter !== null ? ($decoded['params'][$nameParameter] ?? null) : null;

        if (is_string($name)) {
            $headers['Mcp-Name'] = $this->encodeHeaderValue($name);
        }

        return $headers;
    }

    /**
     * Encode a header value using the Base64 sentinel format when it cannot be
     * safely represented as a plain ASCII header value.
     */
    protected function encodeHeaderValue(string $value): string
    {
        $plainAscii = preg_match('/^[\x21-\x7E](?:[\x20-\x7E]*[\x21-\x7E])?$/', $value) === 1;
        $matchesSentinel = str_starts_with($value, '=?base64?') && str_ends_with($value, '?=');

        return $plainAscii && ! $matchesSentinel
            ? $value
            : '=?base64?'.base64_encode($value).'?=';
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
        $this->protocolVersion = null;
        $this->queue = [];
    }

    protected function failWith(string $message): never
    {
        $this->reset();

        throw new ClientException($message);
    }
}
