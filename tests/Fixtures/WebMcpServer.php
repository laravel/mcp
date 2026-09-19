<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server;

class WebMcpServer extends Server
{
    public array $tools = [
        SayHiTool::class,
        StreamingTool::class,
    ];

    public function webMcp(): array
    {
        return [
            SayHiTool::class,
        ];
    }
}
