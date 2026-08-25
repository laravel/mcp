<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SimpleTextTool extends Tool
{
    protected string $name = 'test_simple_text';

    protected string $description = 'Tests simple text content response';

    public function handle(Request $request): Response
    {
        return Response::text('This is a simple text response for testing.');
    }
}
