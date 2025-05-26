<?php

namespace Laravel\Mcp\Contracts\Transport;

interface Stdio
{
    public function write(string $message);

    public function read();
}
