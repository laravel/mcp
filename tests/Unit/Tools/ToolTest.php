<?php

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolResult;

test('the default name is in kebab case', function (): void {
    $tool = new AnotherComplexToolName;
    expect($tool->name())->toBe('another-complex-tool-name');
});

it('returns no annotations by default', function (): void {
    $tool = new TestTool;
    expect($tool->annotations())->toEqual([]);
});

it('can have a custom title', function (): void {
    $tool = new CustomTitleTool;
    expect($tool->toArray()['title'])->toBe('Custom Title Tool');
});

it('can be read only', function (): void {
    $tool = new ReadOnlyTool;
    $annotations = $tool->annotations();
    expect($annotations['readOnlyHint'])->toBeTrue();
});

it('can be closed world', function (): void {
    $tool = new ClosedWorldTool;
    expect($tool->annotations()['openWorldHint'])->toBeFalse();
});

it('can be idempotent', function (): void {
    $tool = new IdempotentTool;
    $annotations = $tool->annotations();
    expect($annotations['idempotentHint'])->toBeTrue();
});

it('can be destructive', function (): void {
    $tool = new DestructiveTool;
    $annotations = $tool->annotations();
    expect($annotations['destructiveHint'])->toBeTrue();
});

it('is not destructive', function (): void {
    $tool = new NotDestructiveTool;
    $annotations = $tool->annotations();
    expect($annotations['destructiveHint'])->toBeFalse();
});

it('can be open world', function (): void {
    $tool = new OpenWorldTool;
    expect($tool->annotations()['openWorldHint'])->toBeTrue();
});

it('can have multiple annotations', function (): void {
    $tool = new KitchenSinkTool;
    expect($tool->annotations())->toEqual([
        'readOnlyHint' => true,
        'idempotentHint' => true,
        'destructiveHint' => false,
        'openWorldHint' => false,
    ]);
});

class TestTool extends Tool
{
    public function description(): string
    {
        return 'A test tool';
    }

    public function handle(): ToolResult|Generator
    {
        return ToolResult::text('test');
    }
}

class CustomTitleTool extends TestTool
{
    protected string $title = 'Custom Title Tool';
}

#[IsReadOnly]
class ReadOnlyTool extends TestTool {}

#[IsOpenWorld(false)]
class ClosedWorldTool extends TestTool {}

#[IsIdempotent]
class IdempotentTool extends TestTool {}

#[IsDestructive]
class DestructiveTool extends TestTool {}

#[IsDestructive(false)]
class NotDestructiveTool extends TestTool {}

#[IsOpenWorld]
class OpenWorldTool extends TestTool {}

#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class KitchenSinkTool extends TestTool
{
    protected string $title = 'The Kitchen Sink';
}

class AnotherComplexToolName extends TestTool {}
