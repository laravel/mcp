<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class ToolWithOutputSchema extends Tool
{
    protected string $description = 'This tool returns user data with output schema';

    public function handle(Request $request): ResponseFactory
    {
        return Response::structured([
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('User ID')->required(),
            'name' => $schema->string()->description('User name')->required(),
            'email' => $schema->string()->description('User email'),
        ];
    }
}
