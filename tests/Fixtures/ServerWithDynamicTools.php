<?php

namespace Tests\Fixtures;

use Laravel\Mcp\Server;

class ServerWithDynamicTools extends Server
{
    public array $tools = [
        //
    ];

    protected function boot(): void
    {
        $this->tools[] = SayHiTool::class;
        $this->tools[] = StreamingTool::class;
    }
}
