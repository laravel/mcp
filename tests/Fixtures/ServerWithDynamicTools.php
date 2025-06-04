<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;
use Laravel\Mcp\Tests\Fixtures\StreamingTool;

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
