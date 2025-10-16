<?php

namespace Tests\Fixtures;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ReturnStructuredContentTool extends Tool
{
    protected string $description = 'This tool returns structured content';

    public function handle(Request $request): array
    {
        $request->validate([
            'name' => 'required|string',
            'age' => 'required|integer',
        ]);

        $name = $request->get('name');
        $age = $request->get('age');

        return [
            Response::structured(['name' => $name]),
            Response::structured(['age' => $age]),
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The name of the person to greet')
                ->required(),
            'age' => $schema->integer()
                ->description('The age of the person')
                ->required(),
        ];
    }
}
