<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Transport\Transport;

class ArrayTransport implements Transport
{
    public $handler = null;
    public array $sent = [];

    public function onReceive(callable $handler)
    {
        $this->handler = $handler;
    }

    public function run()
    {
        //
    }

    public function send(string $message)
    {
        $this->sent[] = $message;
    }
}
