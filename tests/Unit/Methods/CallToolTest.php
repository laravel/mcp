<?php

use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Tests\Fixtures\CurrentTimeTool;
use Tests\Fixtures\SayHiTool;
use Tests\Fixtures\SayHiTwiceTool;
use Tests\Fixtures\SayHiWithMetaTool;

it('returns a valid call tool response', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'say-hi-tool',
            'arguments' => ['name' => 'John Doe'],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [SayHiTool::class],
        resources: [],
        prompts: [],
    );

    $method = new CallTool;

    $this->instance('mcp.request', $request->toRequest());
    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);

    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Hello, John Doe!',
                ],
            ],
            'isError' => false,
        ]);
});

it('returns a valid call tool response that contains two messages', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'say-hi-twice-tool',
            'arguments' => ['name' => 'John Doe'],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [SayHiTwiceTool::class],
        resources: [],
        prompts: [],
    );

    $method = new CallTool;

    $this->instance('mcp.request', $request->toRequest());
    $responses = $method->handle($request, $context);

    [$response] = iterator_to_array($responses);

    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Hello, John Doe!',
                ],
                [
                    'type' => 'text',
                    'text' => 'Hello again, John Doe!',
                ],
            ],
            'isError' => false,
        ]);
});

it('returns a valid call tool response with validation error', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'say-hi-tool',
            'arguments' => ['name' => ''],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [SayHiTool::class],
        resources: [],
        prompts: [],
    );

    $method = new CallTool;

    $this->instance('mcp.request', $request->toRequest());
    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'The name field is required.',
                ],
            ],
            'isError' => true,
        ]);
});

it('includes result meta when responses provide it', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'say-hi-with-meta-tool',
            'arguments' => ['name' => 'John Doe'],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [SayHiWithMetaTool::class],
        resources: [],
        prompts: [],
    );

    $method = new CallTool;

    $this->instance('mcp.request', $request->toRequest());
    $response = $method->handle($request, $context);

    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Hello, John Doe!',
                ],
            ],
            'isError' => false,
            '_meta' => [
                'requestId' => 'abc-123',
                'source' => 'tests/fixtures',
            ],
        ]);
});

it('may resolve dependencies out of the container', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'current-time-tool',
            'arguments' => [],
        ],
    ]);

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

    $this->instance('mcp.request', $request->toRequest());
    $response = $method->handle($request, $context);

    $payload = $response->toArray();
    ['type' => $type, 'text' => $text] = $payload['result']['content'][0];

    expect($response)->toBeInstanceOf(JsonRpcResponse::class)
        ->and($payload['id'])->toEqual(1)
        ->and($type)->toEqual('text')
        ->and($text)->toContain('The current time is ');
});
