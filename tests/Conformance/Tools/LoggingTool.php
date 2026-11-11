<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class LoggingTool extends Tool
{
    protected string $name = 'test_logging_tool';

    protected string $description = 'Tests that no log notifications are sent without a requested log level';

    public function handle(Request $request): Response
    {
        return Response::text('Logging tool completed.');
    }
}
