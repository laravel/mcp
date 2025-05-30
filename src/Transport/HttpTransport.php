<?php

namespace Laravel\Mcp\Transport;

use Illuminate\Http\Request;
use Laravel\Mcp\Contracts\Transport\Transport;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Generator;

class HttpTransport implements Transport
{
    private $handler;
    private ?string $reply = null;
    private Request $request;
    private ?string $sessionId = null;
    private ?string $replySessionId = null;
    private ?Generator $stream = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->sessionId = $request->header('Mcp-Session-Id');
    }

    public function onReceive(callable $handler)
    {
        $this->handler = $handler;
    }

    public function send(string $message, ?string $sessionId = null)
    {
        if ($this->stream) {
            echo 'data: ' . $message . "\n\n";
            flush();
        } else {
            $this->reply = $message;
            $this->replySessionId = $sessionId;
        }
    }

    public function run(): Response|StreamedResponse
    {
        ($this->handler)($this->request->getContent());

        $headers = [
            'Content-Type' => $this->stream ? 'text/event-stream' : 'application/json',
        ];

        if ($this->replySessionId) {
            $headers['Mcp-Session-Id'] = $this->replySessionId;
        }

        if ($this->stream) {
            $headers['X-Accel-Buffering'] = 'no';
        }

        if ($this->stream) {
            return response()->stream(function () {
                foreach ($this->stream as $message) {
                    echo 'data: ' . $message->toJson() . "\n\n";
                    flush();
                }
            }, 200, $headers);
        }

        return response($this->reply, 200, $headers);
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function stream(Generator $stream): void
    {
        $this->stream = $stream;
    }
}
