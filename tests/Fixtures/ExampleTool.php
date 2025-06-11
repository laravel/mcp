<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Tools\Tool;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolResponse;
use Mockery;
use Generator;

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

    public function handle(array $arguments): ToolResponse|Generator
    {
        if (empty($arguments['name'])) {
            $validator = Mockery::mock(Validator::class);
            $validator->shouldReceive('fails')->andReturn(true);
            $validator->shouldReceive('errors')->andReturn(new MessageBag(
                ['name' => ['The name field is required.']]
            ));

            throw new ValidationException($validator);
        }

        return new ToolResponse('Hello, '.$arguments['name'].'!');
    }
}
