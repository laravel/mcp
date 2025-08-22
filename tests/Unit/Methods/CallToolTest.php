<?php

namespace Tests\Unit\Methods;

use Illuminate\Events\Dispatcher;
use Laravel\Mcp\Server\Events\ToolCallFailed;
use Laravel\Mcp\Server\Events\ToolCallFinished;
use Laravel\Mcp\Server\Events\ToolCallStarting;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Mockery as m;

class CallToolTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

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
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
            tools: [ExampleTool::class],
            resources: [],
            prompts: [],
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
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
            tools: [ExampleTool::class],
            resources: [],
            prompts: [],
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

    #[Test]
    public function it_will_call_the_tool_call_starting_and_finished_event()
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
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
            tools: [ExampleTool::class],
            resources: [],
            prompts: [],
        );

        $dispatcherMock = m::mock(Dispatcher::class);
        $dispatcherMock->shouldReceive('dispatch')
            ->once()
            ->with(m::on(function ($event) {
                return $event instanceof ToolCallStarting
                    && $event->toolName === 'example-tool'
                    && $event->arguments === ['name' => 'John Doe'];
            }));
        $dispatcherMock->shouldReceive('dispatch')
            ->once()
            ->with(m::on(function ($event) {
                return $event instanceof ToolCallFinished
                    && $event->toolName === 'example-tool'
                    && $event->arguments === ['name' => 'John Doe'];
            }));

        app()->instance(Dispatcher::class, $dispatcherMock);

        $method = new CallTool;

        $method->handle($request, $context);

        $this->addToAssertionCount(2);
    }

    #[Test]
    public function it_will_call_the_tool_call_starting_and_failed_event()
    {
        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'example-tool',
                'arguments' => [],
            ],
        ]));

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
            tools: [ExampleTool::class],
            resources: [],
            prompts: [],
        );

        $dispatcherMock = m::mock(Dispatcher::class);
        $dispatcherMock->shouldReceive('dispatch')
            ->once()
            ->with(m::on(function ($event) {
                return $event instanceof ToolCallStarting
                    && $event->toolName === 'example-tool'
                    && $event->arguments === [];
            }));
        $dispatcherMock->shouldReceive('dispatch')
            ->once()
            ->with(m::on(function ($event) {
                return $event instanceof ToolCallFailed
                    && $event->toolName === 'example-tool'
                    && $event->arguments === [];
            }));

        app()->instance(Dispatcher::class, $dispatcherMock);

        $method = new CallTool;

        $method->handle($request, $context);

        $this->addToAssertionCount(2);
    }
}
