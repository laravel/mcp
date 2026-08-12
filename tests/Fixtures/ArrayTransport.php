<?php

namespace Tests\Fixtures;

use Closure;
use Laravel\Mcp\Server\Contracts\Transport;

class ArrayTransport implements Transport
{
    public $handler;

    public array $sent = [];

    public array $requestHeaders = [];

    public function requestHeaders(): array
    {
        return $this->requestHeaders;
    }

    public function onReceive(Closure $handler): void
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

    public function stream(Closure $stream): void
    {
        $stream();
    }
}
