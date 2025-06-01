<?php

namespace Laravel\Mcp\Transport;

use Laravel\Mcp\Contracts\Transport\Transport;
use Illuminate\Support\Str;
use Generator;
use Closure;

class StdioTransport implements Transport
{
    private $handler;
    private $stdio;
    private string $sessionId;

    public function __construct(Stdio $stdio)
    {
        $this->stdio = $stdio;
        $this->sessionId = Str::uuid()->toString();
    }

    public function onReceive(callable $handler)
    {
        $this->handler = $handler;
    }

    public function send(string $message)
    {
        $this->stdio->write($message);
    }

    public function run()
    {
        while ($line = $this->stdio->read()) {
            ($this->handler)($line);
        }
    }

    public function sessionId(): string
    {
        return $this->sessionId;
    }

    public function stream(Closure $stream): void
    {
        $stream();
    }
}
