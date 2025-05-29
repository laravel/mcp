<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Contracts\Transport\Transport;
use Illuminate\Support\Str;

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

    public function sessionId(): ?string
    {
        return Str::uuid()->toString();
    }
}
