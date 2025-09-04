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
     * @var callable(string): void
     */
    protected $handler;

    protected ?string $reply = null;

    protected Request $request;

    protected ?string $sessionId = null;

    protected ?string $replySessionId = null;

    protected ?Closure $stream = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->sessionId = $request->header('Mcp-Session-Id');
    }

    public function onReceive(callable $handler): void
    {
        $this->handler = $handler;
    }

    public function send(string $message, ?string $sessionId = null): void
    {
        if ($this->stream) {
            $this->sendStreamMessage($message);
        }

        $this->reply = $message;
        $this->replySessionId = $sessionId;
    }

    public function run(): Response|StreamedResponse
    {
        ($this->handler)($this->request->getContent());

        if ($this->stream) {
            return response()->stream($this->stream, 200, $this->getHeaders());
        }

        return response($this->reply, 200, $this->getHeaders());
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

        if (ob_get_level()) {
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
