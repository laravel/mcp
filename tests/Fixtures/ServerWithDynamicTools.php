<?php

namespace Tests\Fixtures;

use Laravel\Mcp\Server;

class ServerWithDynamicTools extends Server
{
    public array $tools = [
        //
    ];

    public function boot($clientCapabilities = []): void
    {
        $this->addTool(SayHiTool::class);
        $this->addTool(StreamingTool::class);
    }
}
