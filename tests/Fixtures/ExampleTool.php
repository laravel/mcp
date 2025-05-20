<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Contracts\Tool;
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

    public function getInputSchema(): ToolInputSchema
    {
        return (new ToolInputSchema())->addProperty('name', 'string', 'The name of the person to greet', true);
    }

    public function call(array $arguments): ToolResponse
    {
        return new ToolResponse('Hello, '.$arguments['name'].'!');
    }
}
