<?php

namespace Laravel\Mcp\Tests\Unit\Tools;

use Laravel\Mcp\Tools\ToolResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ToolResponseTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_tool_response(): void
    {
        $responseText = 'This is a test response.';
        $toolResponse = new ToolResponse($responseText);

        $expectedArray = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $responseText,
                ],
            ],
            'isError' => false,
        ];

        $this->assertSame($expectedArray, $toolResponse->toArray());
    }

    #[Test]
    public function it_returns_a_valid_error_tool_response(): void
    {
        $responseText = 'This is a test error response.';
        $toolResponse = new ToolResponse($responseText, true);

        $expectedArray = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $responseText,
                ],
            ],
            'isError' => true,
        ];

        $this->assertSame($expectedArray, $toolResponse->toArray());
    }
}
