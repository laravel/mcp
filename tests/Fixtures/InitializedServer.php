<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server;

class InitializedServer extends Server
{
    public array $tools = [
        'hello-tool' => ExampleTool::class,
    ];

    public function boot()
    {
        $this->session->initialized = true;
    }
}
