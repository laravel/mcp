<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server\Resource;

class LastLogLineResource extends Resource
{
    public function description(): string
    {
        return 'The last line of the log file';
    }

    public function read(): string
    {
        return '2025-07-02 12:00:00 Error: Something went wrong.';
    }
}
