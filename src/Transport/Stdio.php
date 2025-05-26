<?php

namespace Laravel\Mcp\Transport;

class Stdio
{
    public function write(string $output): void
    {
        fwrite(STDOUT, $output . PHP_EOL);
    }

    public function read()
    {
        return fgets(STDIN);
    }
}
