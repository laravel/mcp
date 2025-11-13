<?php

namespace Tests\Fixtures;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class ToolWithBothMetaTool extends Tool
{
    protected string $description = 'This tool returns a response with both content-level and result-level meta';

    public function handle(Request $request): ResponseFactory
    {
        return ResponseFactory::make([
            Response::text('First response')->withMeta(['content_index' => 1]),
            Response::text('Second response')->withMeta(['content_index' => 2]),
        ])->withMeta([
            'result_key' => 'result_value',
            'total_responses' => 2,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
