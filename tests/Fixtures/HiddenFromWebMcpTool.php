<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class HiddenFromWebMcpTool extends Tool
{
    protected string $description = 'This tool is never offered to in-page agents';

    public function shouldRegister(): bool
    {
        return ! Mcp::viaWebMcp();
    }

    public function handle(): Response
    {
        return Response::text('Only remote clients see me.');
    }
}
