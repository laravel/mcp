<?php

namespace Tests\Fixtures;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SayHiTool extends Tool
{
    protected string $description = 'This tool says hello to a person';

    public function handle(Request $request): Response
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $name = $request->get('name');

        return Response::text('Hello, '.$name.'!');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The name of the person to greet')
                ->required(),
        ];
    }
}
