<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Closure;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Contracts\Transport\Transport;

class ArrayTransport implements Transport
{
    public $handler = null;

    public array $sent = [];

    public ?string $sessionId = null;

    public function __construct()
    {
        $this->sessionId = Str::uuid()->toString();
    }

    public function onReceive(callable $handler): void
    {
        $this->handler = $handler;
    }

    public function run(): void
    {
        //
    }

    public function send(string $message): void
    {
        $this->sent[] = $message;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function stream(Closure $stream): void
    {
        $stream();
    }
}
