<?php

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Tests\Fixtures\CurrentTimeTool;
use Tests\Fixtures\ResponseFactoryWithStructuredContentTool;
use Tests\Fixtures\SayHiTool;
use Tests\Fixtures\SayHiTwiceTool;
use Tests\Fixtures\SayHiWithMetaTool;
use Tests\Fixtures\StructuredContentTool;
use Tests\Fixtures\StructuredContentWithMetaTool;
use Tests\Fixtures\ToolWithBothMetaTool;
use Tests\Fixtures\ToolWithResultMetaTool;

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

    expect($payload)
        ->toMatchArray([
            'id' => 1,
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello, John Doe!',
                        '_meta' => [
                            'test' => 'metadata',
                        ],
                    ],
                ],
                'isError' => false,
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

it('returns a result with result-level meta when using ResponseFactory', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'tool-with-result-meta-tool',
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
        tools: [ToolWithResultMetaTool::class],
        resources: [],
        prompts: [],
    );

    $method = new CallTool;

    $this->instance('mcp.request', $request->toRequest());
    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);

    $payload = $response->toArray();

    expect($payload)
        ->toMatchArray([
            'id' => 1,
            'result' => [
                '_meta' => [
                    'session_id' => 50,
                ],
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Tool response with result meta',
                    ],
                ],
                'isError' => false,
            ],
        ])
        ->and($payload['result']['_meta'])
        ->toHaveKeys(['session_id']);
});

it('separates content-level meta from result-level meta', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'tool-with-both-meta-tool',
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
        tools: [ToolWithBothMetaTool::class],
        resources: [],
        prompts: [],
    );

    $method = new CallTool;

    $this->instance('mcp.request', $request->toRequest());
    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);

    $payload = $response->toArray();

    expect($payload)
        ->toMatchArray([
            'result' => [
                'isError' => false,
                '_meta' => [
                    'result_key' => 'result_value',
                    'total_responses' => 2,
                ],
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'First response',
                        '_meta' => ['content_index' => 1],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Second response',
                        '_meta' => ['content_index' => 2],
                    ],
                ],
            ],
        ]);
});

it('returns structured content in tool response', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'structured-content-tool',
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
        tools: [StructuredContentTool::class],
        resources: [],
        prompts: [],
    );

    $method = new CallTool;

    $this->instance('mcp.request', $request->toRequest());
    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);

    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toHaveKey('structuredContent')
        ->and($payload['result']['structuredContent'])->toEqual([
            'temperature' => 22.5,
            'conditions' => 'Partly cloudy',
            'humidity' => 65,
        ])
        ->and($payload['result']['content'])->toHaveCount(1)
        ->and($payload['result']['content'][0]['type'])->toBe('text')
        ->and($payload['result']['content'][0]['text'])->toContain('"temperature": 22.5')
        ->and($payload['result']['isError'])->toBeFalse();
});

it('returns structured content with meta in tool response', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'structured-content-with-meta-tool',
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
        tools: [StructuredContentWithMetaTool::class],
        resources: [],
        prompts: [],
    );

    $method = new CallTool;

    $this->instance('mcp.request', $request->toRequest());
    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);

    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toHaveKey('structuredContent')
        ->and($payload['result']['structuredContent'])->toEqual([
            'result' => 'The operation completed successfully',
        ])
        ->and($payload['result'])->toHaveKey('_meta')
        ->and($payload['result']['_meta'])->toEqual(['requestId' => 'abc123'])
        ->and($payload['result']['content'])->toHaveCount(1)
        ->and($payload['result']['isError'])->toBeFalse();
});

it('returns ResponseFactory with structured content added via withStructuredContent', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'response-factory-with-structured-content-tool',
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
        tools: [ResponseFactoryWithStructuredContentTool::class],
        resources: [],
        prompts: [],
    );

    $method = new CallTool;

    $this->instance('mcp.request', $request->toRequest());
    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);

    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toHaveKey('structuredContent')
        ->and($payload['result']['structuredContent'])->toEqual([
            'status' => 'success',
            'code' => 200,
        ])
        ->and($payload['result']['content'])->toHaveCount(1)
        ->and($payload['result']['content'][0]['type'])->toBe('text')
        ->and($payload['result']['content'][0]['text'])->toBe('Processing complete with status: success')
        ->and($payload['result']['isError'])->toBeFalse();
});

it('throws an exception when the name parameter is missing', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
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

    expect(fn (): Generator|JsonRpcResponse => $method->handle($request, $context))
        ->toThrow(
            JsonRpcException::class,
            'Missing [name] parameter.'
        );
});

it('throws an exception when the tool is not found', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'non-existent-tool',
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

    expect(fn (): Generator|JsonRpcResponse => $method->handle($request, $context))
        ->toThrow(
            JsonRpcException::class,
            'Tool [non-existent-tool] not found.'
        );
});

it('does not set uri on request when calling tools', function (): void {
    $tool = new class extends Tool
    {
        protected string $description = 'Test tool';

        public function handle(Request $request): Response
        {
            return Response::text(json_encode($request->uri()));
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };

    $toolClass = $tool::class;
    $this->instance($toolClass, $tool);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool->name(),
            'arguments' => [],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test',
        serverVersion: '1.0.0',
        instructions: '',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [$toolClass],
        resources: [],
        prompts: [],
    );

    $this->instance('mcp.request', $request->toRequest());

    $method = new CallTool;
    $payload = ($method->handle($request, $context))->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result']['content'])->toHaveCount(1)
        ->and($payload['result']['content'][0]['type'])->toBe('text')
        ->and($payload['result']['content'][0]['text'])->toBe('null');
});
