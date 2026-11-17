<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server;

class MirroredHeaderServer extends Server
{
    public array $tools = [
        MirroredHeaderTool::class,
    ];
}
