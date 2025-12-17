<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Mcp\Server\Contracts\Transport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HttpTransport implements Transport
{
    /**
     * @param  (Closure(string): void)|null  $handler
     */
    public function __construct(
        protected Request $request,
        protected string $sessionId,
        protected ?Closure $handler = null,
        protected ?string $reply = null,
        protected ?string $replySessionId = null,
        protected ?Closure $stream = null,
    ) {
        //
    }

    public function onReceive(Closure $handler): void
    {
        $this->handler = $handler;
    }

    public function send(string $message, ?string $sessionId = null): void
    {
        if ($this->stream instanceof Closure) {
            $this->sendStreamMessage($message);
        }

        $this->reply = $message;
        $this->replySessionId = $sessionId;
    }

    public function run(): Response|StreamedResponse
    {
        if (is_callable($this->handler)) {
            ($this->handler)($this->request->getContent());
        }

        if ($this->stream instanceof Closure) {
            return response()->stream($this->stream, 200, $this->getHeaders());
        }

        // Must be 202 - https://modelcontextprotocol.io/specification/2025-06-18/basic/transports#sending-messages-to-the-server
        $statusCode = $this->reply === null ? 202 : 200;
        $response = response($this->reply, $statusCode, $this->getHeaders());

        assert($response instanceof Response);

        return $response;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function stream(Closure $stream): void
    {
        $this->stream = $stream;
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

        if ($this->replySessionId !== null) {
            $headers['MCP-Session-Id'] = $this->replySessionId;
        }

        if ($this->stream instanceof Closure) {
            $headers['X-Accel-Buffering'] = 'no';
        }

        return $headers;
    }
}
