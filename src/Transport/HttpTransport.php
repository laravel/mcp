<?php

namespace Laravel\Mcp\Transport;

use Illuminate\Http\Request;
use Laravel\Mcp\Contracts\Transport\Transport;
use Illuminate\Http\Response;

class HttpTransport implements Transport
{
    private $handler;
    private ?string $reply = null;
    private Request $request;
    private ?string $sessionId = null;
    private ?string $replySessionId = null;

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
        $this->reply = $message;
        $this->replySessionId = $sessionId;
    }

    public function run(): Response
    {
        ($this->handler)($this->request->getContent());

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->replySessionId) {
            $headers['Mcp-Session-Id'] = $this->replySessionId;
        }

        return response($this->reply, 200, $headers);
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }
}
