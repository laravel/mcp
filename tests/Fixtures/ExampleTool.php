<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Generator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Mockery;

class ExampleTool extends Tool
{
    public function description(): string
    {
        return 'This tool says hello to a person';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('name')->description('The name of the person to greet')->required();
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        if (empty($arguments['name'])) {
            $validator = Mockery::mock(Validator::class);
            $validator->shouldReceive('fails')->andReturn(true);
            $validator->shouldReceive('errors')->andReturn(new MessageBag(
                ['name' => ['The name field is required.']]
            ));

            throw new ValidationException($validator);
        }

        return ToolResult::text('Hello, '.$arguments['name'].'!');
    }
}
