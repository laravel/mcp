<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class EmbeddedResourceTool extends Tool
{
    protected string $description = 'Returns the daily plan and standup notes inline.';

    public function handle(Request $request): ResponseFactory
    {
        return Response::make([
            Response::text('Here are the notes.'),
            Response::embeddedResource(DailyPlanResource::class),
            Response::text('# Standup')->asResource('file:///notes/standup.md', 'text/markdown', ['audience' => ['assistant']]),
        ]);
    }
}
