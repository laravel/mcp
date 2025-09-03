<?php

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

test('the default name is in kebab case', function () {
    $tool = new AnotherComplexToolName;
    expect($tool->name())->toBe('another-complex-tool-name');
});

it('returns no annotations by default', function () {
    $tool = new TestTool;
    expect($tool->annotations())->toEqual([]);
});

it('can have multiple annotations', function () {
    $tool = new KitchenSinkTool;
    expect($tool->annotations())->toEqual([
        'title' => 'The Kitchen Sink',
        'readOnlyHint' => true,
        'idempotentHint' => true,
    ]);
});

class TestTool extends Tool
{
    public function description(): string
    {
        return 'A test tool';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema;
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        return ToolResult::text('test');
    }
}

class CustomTitleTool extends TestTool
{
    protected string $title = 'Custom Title Tool';
}

class ReadOnlyTool extends TestTool
{
    protected bool $readonly = true;
}

class ClosedWorldTool extends TestTool
{
    protected bool $openWorld = false;
}

class IdempotentTool extends TestTool
{
    protected bool $idempotent = true;
}

class DestructiveTool extends TestTool
{
    protected bool $destructive = true;
}

class NotDestructiveTool extends TestTool
{
    protected bool $destructive = false;
}

class OpenWorldTool extends TestTool
{
    protected bool $openWorld = true;
}

class KitchenSinkTool extends TestTool
{
    protected string $title = 'The Kitchen Sink';

    protected bool $readonly = true;

    protected bool $idempotent = true;
}

class AnotherComplexToolName extends TestTool {}
