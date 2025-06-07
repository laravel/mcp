<?php

namespace Laravel\Mcp\Contracts\Transport;

use Closure;

interface Transport
{
    public function onReceive(callable $handler);

    public function run();

    public function send(string $message);

    public function sessionId(): ?string;

    public function stream(Closure $stream);
}
