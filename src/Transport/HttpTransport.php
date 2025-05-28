<?php

namespace Laravel\Mcp\Transport;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Mcp\Contracts\Transport\Transport;

class HttpTransport implements Transport
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

    public function run(): Response
    {
        ($this->handler)($this->request->getContent());

        return response($this->reply, 200)->header('Content-Type', 'application/json');
    }
}
