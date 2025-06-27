<?php

namespace Laravel\Mcp\Tests\Unit\Tools;

use Generator;
use Laravel\Mcp\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Tools\Annotations\Title;
use Laravel\Mcp\Tools\Tool;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolResult;
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
    public function it_can_be_idempotent_and_not_destructive()
    {
        $tool = new SafeTool;
        $annotations = $tool->annotations();
        $this->assertArrayNotHasKey('readOnlyHint', $annotations);
        $this->assertFalse($annotations['destructiveHint']);
        $this->assertTrue($annotations['idempotentHint']);
    }

    #[Test]
    public function it_can_be_closed_world()
    {
        $tool = new ClosedWorldTool;
        $this->assertFalse($tool->annotations()['openWorldHint']);
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

#[IsIdempotent]
#[IsDestructive(false)]
class SafeTool extends TestTool {}

#[IsOpenWorld(false)]
class ClosedWorldTool extends TestTool {}

class AnotherComplexToolName extends TestTool {}
