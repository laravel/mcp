<?php

namespace Tests\Fixtures;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolResult;

class CurrentTimeTool extends Tool
{
    protected string $description = 'This tool gets the current time';

    public function handle(Clock $clock): ToolResult
    {
        return ToolResult::text(
            'The current time is '.$clock->now().'.'
        );
    }
}
