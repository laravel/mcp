<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Support\ToolHeaders;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HttpTransport implements Transport
{
    /**
     * @param  (Closure(string): void)|null  $handler
     */
    public function __construct(
        protected Request $request,
        protected ?Closure $handler = null,
        protected ?string $reply = null,
        protected ?Closure $stream = null,
    ) {
        //
    }

    public function onReceive(Closure $handler): void
    {
        $this->handler = $handler;
    }

    public function send(string $message): void
    {
        if ($this->stream instanceof Closure) {
            $this->sendStreamMessage($message);
        }

        $this->reply = $message;
    }

    public function run(): Response|StreamedResponse
    {
        if (is_callable($this->handler)) {
            ($this->handler)($this->request->getContent());
        }

        if ($this->stream instanceof Closure) {
            $stream = $this->stream;

            return response()->stream(function () use ($stream): void {
                $result = $stream();

                if (! is_iterable($result)) {
                    return;
                }

                foreach ($result as $message) {
                    if (connection_aborted() !== 0) {
                        return;
                    }

                    $this->sendStreamMessage((string) $message);
                }
            }, 200, $this->getHeaders());
        }

        $response = response($this->reply, $this->statusCode(), $this->getHeaders());

        assert($response instanceof Response);

        return $response;
    }

    protected function statusCode(): int
    {
        // Must be 202 - https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http#sending-messages
        if ($this->reply === null) {
            return 202;
        }

        $reply = json_decode($this->reply, true);

        if (! is_array($reply) || ! is_array($reply['error'] ?? null)) {
            return 200;
        }

        return match ($reply['error']['code'] ?? null) {
            ErrorCode::METHOD_NOT_FOUND->value => 404,
            ErrorCode::INTERNAL_ERROR->value => 500,
            default => 400,
        };
    }

    /**
     * Register a streaming callback.
     *
     * The callback may echo SSE-formatted output directly or return an iterable of message payloads.
     *
     * @param  Closure(): (iterable<string>|void)  $stream
     */
    public function stream(Closure $stream): void
    {
        $this->stream = $stream;
    }

    public function validateToolHeaders(mixed $inputSchema, mixed $arguments, int|string $id): void
    {
        if (! is_array($inputSchema) || ! is_array($arguments)) {
            return;
        }

        foreach (ToolHeaders::extract($inputSchema, $arguments) as $header => $expected) {
            $actual = $this->request->header($header);

            if (! is_string($actual) || $actual === '') {
                throw new JsonRpcException(
                    "Header mismatch: The [{$header}] header is required.",
                    ErrorCode::HEADER_MISMATCH->value,
                    $id,
                );
            }

            if ($actual !== $expected) {
                throw new JsonRpcException(
                    "Header mismatch: The [{$header}] header value [{$actual}] does not match the request body value [{$expected}].",
                    ErrorCode::HEADER_MISMATCH->value,
                    $id,
                );
            }
        }
    }

    protected function sendStreamMessage(string $message): void
    {
        echo 'data: '.$message."\n\n";

        if (ob_get_level() !== 0) {
            ob_flush();
        }

        flush();
    }

    /**
     * @return array<string, string>
     */
    protected function getHeaders(): array
    {
        $headers = [
            'Content-Type' => $this->stream instanceof Closure ? 'text/event-stream' : 'application/json',
        ];

        if ($this->stream instanceof Closure) {
            $headers['X-Accel-Buffering'] = 'no';
        }

        return $headers;
    }
}
