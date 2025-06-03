<?php

namespace Tests\Unit\Methods;

use Laravel\Mcp\Methods\CallTool;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CallToolTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_call_tool_response()
    {
        $message = JsonRpcMessage::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'hello-tool',
                'arguments' => ['name' => 'John Doe'],
            ],
        ]));

        $context = new SessionContext(
            supportedProtocolVersions: ['2025-03-26'],
            clientCapabilities: [],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            tools: ['hello-tool' => ExampleTool::class],
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
        );

        $method = new CallTool();

        $response = $method->handle($message, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $response);
        $this->assertEquals(1, $response->id);
        $this->assertEquals([
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Hello, John Doe!',
                ],
            ],
            'isError' => false,
        ], $response->result);
    }

    #[Test]
    public function it_returns_a_valid_call_tool_response_with_validation_error()
    {
        $message = JsonRpcMessage::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'hello-tool',
                'arguments' => ['name' => ''],
            ],
        ]));

        $context = new SessionContext(
            supportedProtocolVersions: ['2025-03-26'],
            clientCapabilities: [],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            tools: ['hello-tool' => ExampleTool::class],
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
        );

        $method = new CallTool();

        $response = $method->handle($message, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $response);
        $this->assertEquals(1, $response->id);
        $this->assertEquals([
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'The name field is required.',
                ],
            ],
            'isError' => true,
        ], $response->result);
    }
}
