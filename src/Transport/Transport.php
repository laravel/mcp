<?php

namespace Laravel\Mcp\Transport;

interface Transport
{
    public function onReceive(callable $handler);

    public function run();

    public function send(string $message);
}
