<?php

namespace Laravel\Mcp\Mcp\Transport;

interface Transport
{
    public function onReceive(callable $handler);

    public function run();

    public function send(string $message);
}
