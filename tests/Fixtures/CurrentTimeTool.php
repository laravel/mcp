<?php

namespace Tests\Fixtures;

use Laravel\Mcp\Server\Tool;

class CurrentTimeTool extends Tool
{
    protected string $description = 'This tool gets the current time';

    public function handle(Clock $clock): string
    {
        return 'The current time is '.$clock->now();
    }
}
