<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Transport\Transport;

class ArrayTransport implements Transport
{
    public $receivedMessageHandler;
    public $sentMessages = [];

    public function onReceive(callable $handler)
    {
        $this->receivedMessageHandler = $handler;
    }

    public function run()
    {
        // Not needed for this test
    }

    public function send(string $message)
    {
        $this->sentMessages[] = $message;
    }

    // Helper to simulate receiving a message
    public function simulateReceive(string $message)
    {
        if ($this->receivedMessageHandler) {
            call_user_func($this->receivedMessageHandler, $message);
        }
    }
}
