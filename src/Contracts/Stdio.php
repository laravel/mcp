<?php

namespace Laravel\Mcp\Contracts;

interface Stdio
{
    public function write(string $message);

    public function read();
}
