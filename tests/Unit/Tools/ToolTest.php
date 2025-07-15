<?php

namespace Laravel\Mcp\Tests\Unit\Tools;

use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ToolTest extends TestCase
{
    #[Test]
    public function the_default_name_is_in_kebab_case()
    {
        $tool = new AnotherComplexToolName;
        $this->assertSame('another-complex-tool-name', $tool->name());
    }

    #[Test]
    public function it_returns_no_annotations_by_default()
    {
        $tool = new TestTool;
        $this->assertEquals([], $tool->annotations());
    }

    #[Test]
    public function it_can_have_a_custom_title()
    {
        $tool = new CustomTitleTool;
        $this->assertSame('Custom Title Tool', $tool->annotations()['title']);
    }

    #[Test]
    public function it_can_be_read_only()
    {
        $tool = new ReadOnlyTool;
        $annotations = $tool->annotations();
        $this->assertTrue($annotations['readOnlyHint']);
    }

    #[Test]
    public function it_can_be_closed_world()
    {
        $tool = new ClosedWorldTool;
        $this->assertFalse($tool->annotations()['openWorldHint']);
    }

    #[Test]
    public function it_can_be_idempotent()
    {
        $tool = new IdempotentTool;
        $annotations = $tool->annotations();
        $this->assertTrue($annotations['idempotentHint']);
    }

    #[Test]
    public function it_can_be_destructive()
    {
        $tool = new DestructiveTool;
        $annotations = $tool->annotations();
        $this->assertTrue($annotations['destructiveHint']);
    }

    #[Test]
    public function it_is_not_destructive()
    {
        $tool = new NotDestructiveTool;
        $annotations = $tool->annotations();
        $this->assertFalse($annotations['destructiveHint']);
    }

    #[Test]
    public function it_can_be_open_world()
    {
        $tool = new OpenWorldTool;
        $this->assertTrue($tool->annotations()['openWorldHint']);
    }

    #[Test]
    public function it_can_have_multiple_annotations()
    {
        $tool = new KitchenSinkTool;
        $this->assertEquals([
            'title' => 'The Kitchen Sink',
            'readOnlyHint' => true,
            'idempotentHint' => true,
            'destructiveHint' => false,
            'openWorldHint' => false,
        ], $tool->annotations());
    }
}

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

#[Title('Custom Title Tool')]
class CustomTitleTool extends TestTool {}

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

#[Title('The Kitchen Sink')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class KitchenSinkTool extends TestTool {}

class AnotherComplexToolName extends TestTool {}
