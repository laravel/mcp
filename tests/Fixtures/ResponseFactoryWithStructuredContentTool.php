<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class ResponseFactoryWithStructuredContentTool extends Tool
{
    protected string $description = 'This tool returns a ResponseFactory with structured content';

    public function handle(Request $request): ResponseFactory
    {
        return Response::make([
            Response::text('Processing complete with status: success'),
        ])->withStructuredContent([
            'status' => 'success',
            'code' => 200,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
