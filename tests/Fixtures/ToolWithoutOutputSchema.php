<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ToolWithoutOutputSchema extends Tool
{
    protected string $description = 'This tool does not define an output schema';

    public function handle(Request $request): Response
    {
        return Response::text('Simple text response without schema');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
