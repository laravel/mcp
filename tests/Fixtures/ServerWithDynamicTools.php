<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server;

class ServerWithDynamicTools extends Server
{
    public array $tools = [
        //
    ];

    public function boot($clientCapabilities = [])
    {
        $this->addTool(ExampleTool::class);
        $this->addTool(StreamingTool::class);
    }
}
