<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Generator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ProgressTool extends Tool
{
    protected string $name = 'test_tool_with_progress';

    protected string $description = 'Tests that a tool reports progress notifications';

    public function handle(Request $request): Generator
    {
        yield Response::progress(0, total: 100);

        usleep(50_000);

        yield Response::progress(50, total: 100);

        usleep(50_000);

        yield Response::progress(100, total: 100);

        yield Response::text('Progress tool completed.');
    }
}
