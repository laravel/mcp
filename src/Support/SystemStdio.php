<?php

namespace Laravel\Mcp\Support;

use Laravel\Mcp\Contracts\Transport\Stdio;

class SystemStdio implements Stdio
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
