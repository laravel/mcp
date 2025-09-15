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
        $this->addTool(SayHiTool::class);
        $this->addTool(StreamingTool::class);
    }
}
