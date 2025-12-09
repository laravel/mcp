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

    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
        self::CAPABILITY_RESOURCES => [
            'listChanged' => false,
        ],
        self::CAPABILITY_PROMPTS => [
            'listChanged' => false,
        ],
        self::CAPABILITY_LOGGING => [],
    ];

    protected function generateSessionId(): string
    {
        return 'overridden-'.uniqid();
    }
}
