<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class TaggedTool extends Tool
{
    protected string $description = 'A tool tagged with weather';

    protected array $tags = ['weather'];

    public function handle(): Response
    {
        return Response::text('tagged');
    }
}
