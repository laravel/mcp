<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Contracts\Tools\Tool;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolResponse;

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
        return new ToolResponse('Hello, '.$arguments['name'].'!');
    }
}
