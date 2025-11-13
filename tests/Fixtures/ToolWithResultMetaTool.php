<?php

namespace Tests\Fixtures;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class ToolWithResultMetaTool extends Tool
{
    protected string $description = 'This tool returns a response with result-level meta';

    public function handle(Request $request): ResponseFactory
    {
        return ResponseFactory::make(
            Response::text('Tool response with result meta')
        )->withMeta([
            'session_id' => $request->sessionId(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
