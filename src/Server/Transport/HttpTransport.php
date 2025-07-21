<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Mcp\Server\Contracts\Transport\Transport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HttpTransport implements Transport
{
    /**
     * The server handler responsible for handling the request.
     */
    private $handler;

    /**
     * The reply to the request (for non-streaming responses).
     */
    private ?string $reply = null;

    /**
     * The request object.
     */
    private Request $request;

    /**
     * The session ID of the request.
     */
    private ?string $sessionId = null;

    /**
     * The session ID of the reply (if provided by the client).
     */
    private ?string $replySessionId = null;

    /**
     * The stream callback for yielding stream messages.
     */
    private ?Closure $stream = null;

    /**
     * Create a new HTTP transport.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->sessionId = $request->header('Mcp-Session-Id');
    }

    /**
     * Set the server handler to handle incoming messages.
     */
    public function onReceive(callable $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * Send a message to the client.
     */
    public function send(string $message, ?string $sessionId = null): void
    {
        if ($this->stream) {
            $this->sendStreamMessage($message);
        }

        $this->reply = $message;
        $this->replySessionId = $sessionId;
    }

    /**
     * Run the transport and process the request.
     */
    public function run(): Response|StreamedResponse
    {
        ($this->handler)($this->request->getContent());

        if ($this->stream) {
            return response()->stream($this->stream, 200, $this->getHeaders());
        }

        return response($this->reply, 200, $this->getHeaders());
    }

    /**
     * Get the session ID of the request.
     */
    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Stream the yielded values from the callback.
     */
    public function stream(Closure $stream): void
    {
        $this->stream = $stream;
    }

    /**
     * Stream a message to the client.
     */
    private function sendStreamMessage(string $message): void
    {
        echo 'data: '.$message."\n\n";

        if (ob_get_level()) {
            ob_flush();
        }

        flush();
    }

    /**
     * Get the headers for the response.
     */
    private function getHeaders(): array
    {
        $headers = [
            'Content-Type' => $this->stream ? 'text/event-stream' : 'application/json',
        ];

        if ($this->replySessionId) {
            $headers['Mcp-Session-Id'] = $this->replySessionId;
        }

        if ($this->stream) {
            $headers['X-Accel-Buffering'] = 'no';
        }

        return $headers;
    }
}
