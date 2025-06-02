<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;
use Laravel\Mcp\Tests\Fixtures\StreamingTool;

class ExampleServer extends Server
{
    public array $tools = [
        'hello-tool' => ExampleTool::class,
        'streaming-tool' => StreamingTool::class,
    ];
}
