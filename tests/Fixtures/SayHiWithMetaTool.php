<?php

namespace Tests\Fixtures;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SayHiWithMetaTool extends Tool
{
    protected string $description = 'This tool says hello to a person with metadata';

    protected ?array $meta = [
        'requestId' => 'abc-123',
        'source' => 'tests/fixtures',
    ];

    public function handle(Request $request): Response
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $name = $request->get('name');

        return Response::text('Hello, '.$name.'!')->withMeta([
            'test' => 'metadata',
        ]);
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
