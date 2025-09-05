<?php

namespace Tests\Fixtures;

use Generator;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolResult;

class ExampleTool extends Tool
{
    protected string $description = 'This tool says hello to a person';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The name of the person to greet')
                ->required(),
        ];
    }

    public function handle(Request $request): ToolResult|Generator
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $name = $request->get('name');

        return ToolResult::text('Hello, '.$name.'!');
    }
}
