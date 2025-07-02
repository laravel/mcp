<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server;

class ExampleServer extends Server
{
    public array $tools = [
        ExampleTool::class,
        StreamingTool::class,
    ];

    public array $resources = [
        LastLogLineResource::class,
        DailyPlanResource::class,
        RecentMeetingRecordingResource::class,
    ];
}
