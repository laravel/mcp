<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server;

class ServerWithMirroredParameters extends Server
{
    public array $tools = [
        ExecuteSqlTool::class,
    ];
}
