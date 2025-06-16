<?php

namespace Laravel\Mcp\Transport;

class Stdio
{
    /**
     * Write a message to standard output.
     */
    public function write(string $output): void
    {
        fwrite(STDOUT, $output . PHP_EOL);
    }

    /**
     * Read a message from standard input.
     */
    public function read()
    {
        return fgets(STDIN);
    }
}
