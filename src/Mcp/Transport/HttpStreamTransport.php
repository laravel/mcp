<?php

namespace Laravel\Mcp\Mcp\Transport;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HttpStreamTransport implements Transport
{
    private $handler;
    private ?string $reply = null;
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function onReceive(callable $handler)
    {
        $this->handler = $handler;
    }

    public function send(string $message)
    {
        $this->reply = $message;
    }

    public function run(): StreamedResponse
    {
        ($this->handler)($this->request->getContent());

        $callback = function () {
            echo $this->reply ?? '';

            flush();
        };

        $headers = [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream($callback, 200, $headers);
    }
}
