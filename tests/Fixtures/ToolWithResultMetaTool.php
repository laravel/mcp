<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class ToolWithResultMetaTool extends Tool
{
    protected string $description = 'This tool returns a response with result-level meta';

    public function handle(Request $request): ResponseFactory
    {
        return Response::make(
            Response::text('Tool response with result meta')
        )->withMeta([
            'session_id' => 50,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
