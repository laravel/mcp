<?php

use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;

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

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    expect($response->id)->toEqual(1);
    expect($response->result)->toEqual([
        'content' => [
            [
                'type' => 'text',
                'text' => 'The name field is required.',
            ],
        ],
        'isError' => true,
    ]);
});
