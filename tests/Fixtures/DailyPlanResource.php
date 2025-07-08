<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server\Resource;

class DailyPlanResource extends Resource
{
    public function description(): string
    {
        return 'The plan for the day';
    }

    public function read(): string
    {
        // Dummy markdown content representing the daily plan.
        return "# Daily Plan\n\n- [ ] Task 1\n- [ ] Task 2\n- [ ] Task 3";
    }

    public function uri(): string
    {
        return 'file://resources/daily-plan.md';
    }

    public function mimeType(): string
    {
        return 'text/markdown';
    }
}
