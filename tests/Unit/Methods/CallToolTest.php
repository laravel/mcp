<?php

namespace Tests\Unit\Methods;

use Laravel\Mcp\Methods\CallTool;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CallToolTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_call_tool_response()
    {
        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'example-tool',
                'arguments' => ['name' => 'John Doe'],
            ],
        ]));

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            tools: [ExampleTool::class],
            resources: [],
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
        );

        $method = new CallTool;

        $response = $method->handle($request, $context);

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
        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'example-tool',
                'arguments' => ['name' => ''],
            ],
        ]));

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            tools: [ExampleTool::class],
            resources: [],
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
        );

        $method = new CallTool;

        $response = $method->handle($request, $context);

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
