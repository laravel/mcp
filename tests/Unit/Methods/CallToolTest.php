<?php

use Illuminate\Events\Dispatcher;
use Laravel\Mcp\Server\Events\ToolCallFailed;
use Laravel\Mcp\Server\Events\ToolCallFinished;
use Laravel\Mcp\Server\Events\ToolCallStarting;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Mockery as m;
use Tests\Fixtures\CurrentTimeTool;
use Tests\Fixtures\ExampleTool;

it('returns a valid call tool response', function () {
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

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    expect($response->id)->toEqual(1);
    expect($response->result)->toEqual([
        'content' => [
            [
                'type' => 'text',
                'text' => 'Hello, John Doe!',
            ],
        ],
        'isError' => false,
    ]);
});

it('returns a valid call tool response with validation error', function () {
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

    expect($response)->toBeInstanceOf(JsonRpcResponse::class)
        ->and($response->id)->toEqual(1)
        ->and($response->result)->toEqual([
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'The name field is required.',
                ],
            ],
            'isError' => true,
        ]);
});

it('may resolve dependencies out of the container', function () {
    $request = JsonRpcRequest::fromJson(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'current-time-tool',
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
        tools: [CurrentTimeTool::class],
        resources: [],
        prompts: [],
    );

    $method = new CallTool;

    $response = $method->handle($request, $context);

    ['type' => $type, 'text' => $text] = $response->result['content'][0];

    expect($response)->toBeInstanceOf(JsonRpcResponse::class)
        ->and($response->id)->toEqual(1)
        ->and($type)->toEqual('text')
        ->and($text)->toContain('The current time is ');
});

it('will call the tool call starting and finished event', function () {
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
});

it('will call the tool call starting and failed event', function () {
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
});
