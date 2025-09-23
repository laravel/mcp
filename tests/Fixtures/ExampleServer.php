<?php

namespace Tests\Fixtures;

use Laravel\Mcp\Server;

class ExampleServer extends Server
{
    public array $tools = [
        SayHiTool::class,
        StreamingTool::class,
    ];

    public array $resources = [
        LastLogLineResource::class,
        DailyPlanResource::class,
        RecentMeetingRecordingResource::class,
    ];

    protected function generateSessionId(): string
    {
        return 'overridden-'.uniqid();
    }
}
