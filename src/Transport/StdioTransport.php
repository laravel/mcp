<?php

namespace Laravel\Mcp\Transport;

use Laravel\Mcp\Contracts\Transport\Transport;

class StdioTransport implements Transport
{
    private $handler;
    private $stdio;

    public function __construct(Stdio $stdio)
    {
        $this->stdio = $stdio;
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
}
