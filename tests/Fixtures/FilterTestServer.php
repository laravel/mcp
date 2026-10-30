<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server;

class FilterTestServer extends Server
{
    protected array $tools = [
        TaggedTool::class,
        SayHiTool::class,
    ];
}
