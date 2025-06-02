<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Contracts\Tools\Tool;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolResponse;
use Mockery;

class ExampleTool implements Tool
{
    public function getName(): string
    {
        return 'hello-tool';
    }

    public function getDescription(): string
    {
        return 'This tool says hello to a person';
    }

    public function getInputSchema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('name')->description('The name of the person to greet')->required();
    }

    public function call(array $arguments): ToolResponse
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
