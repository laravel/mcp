<?php

namespace Laravel\Mcp\Tests\Unit\Tools;

use Laravel\Mcp\Server\Tools\TextContent;
use Laravel\Mcp\Server\Tools\ToolResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ToolResultTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_tool_result(): void
    {
        $responseText = 'This is a test response.';
        $response = ToolResult::text($responseText);

        $expectedArray = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $responseText,
                ],
            ],
            'isError' => false,
        ];

        $this->assertSame($expectedArray, $response->toArray());
    }

    #[Test]
    public function it_returns_a_valid_error_tool_result(): void
    {
        $responseText = 'This is a test error response.';
        $response = ToolResult::error($responseText);

        $expectedArray = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $responseText,
                ],
            ],
            'isError' => true,
        ];

        $this->assertSame($expectedArray, $response->toArray());
    }

    #[Test]
    public function it_can_handle_multiple_content_items(): void
    {
        $plainText = 'This is the plain text version.';
        $markdown = 'This is the **markdown** version.';

        $response = ToolResult::items(
            new TextContent($plainText),
            new TextContent($markdown)
        );

        $expectedArray = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $plainText,
                ],
                [
                    'type' => 'text',
                    'text' => $markdown,
                ],
            ],
            'isError' => false,
        ];

        $this->assertSame($expectedArray, $response->toArray());
    }
}
