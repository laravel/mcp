<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class StructuredContentWithMetaTool extends Tool
{
    protected string $description = 'This tool returns structured content with meta';

    public function handle(Request $request): ResponseFactory
    {
        return Response::structured([
            'result' => 'The operation completed successfully',
        ])->withMeta(['requestId' => 'abc123']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
