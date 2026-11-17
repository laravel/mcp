<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class StreamingElicitationTool extends Tool
{
    protected string $name = 'test_streaming_elicitation';

    protected string $description = 'Tests that the response stream never carries independent requests';

    public function handle(Request $request): Response
    {
        return Response::text('Streaming elicitation tool completed.');
    }
}
