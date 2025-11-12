<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Tests\Fixtures\SayHiTool;
use Tests\Fixtures\SayHiWithMetaTool;

if (! class_exists('Tests\\Unit\\Methods\\DummyTool1')) {
    for ($i = 1; $i <= 12; $i++) {
        eval("
            namespace Tests\\Unit\\Methods;
            use Generator;
            use Illuminate\JsonSchema\JsonSchema;
            use Laravel\\Mcp\\Server\\Tool;
            use Laravel\\Mcp\\Server\\Tools\\ToolResult;
            class DummyTool{$i} extends Tool {
                public function description(): string { return 'Description for dummy tool {$i}'; }
                public function handle(array \$arguments): ToolResult|Generator { return []; }
            }
        ");
    }
}

it('returns a valid list tools response', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => [],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [SayHiTool::class],
        resources: [],
        prompts: [],
    );

    $listTools = new ListTools;

    $response = $listTools->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'tools' => [
                [
                    'name' => 'say-hi-tool',
                    'description' => 'This tool says hello to a person',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'The name of the person to greet',
                            ],
                        ],
                        'required' => ['name'],
                    ],
                    'annotations' => (object) [],
                    'title' => 'Say Hi Tool',
                ],
            ],
        ]);
});

it('handles pagination correctly', function (): void {
    $toolClasses = [];
    for ($i = 1; $i <= 12; $i++) {
        $toolClasses[] = "Tests\\Unit\\Methods\\DummyTool{$i}";
    }

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: $toolClasses,
        resources: [],
        prompts: [],
    );

    $listTools = new ListTools;

    $firstListToolsRequest = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => [],
    ]);

    $firstPageResponse = $listTools->handle($firstListToolsRequest, $context);

    $firstPayload = $firstPageResponse->toArray();
    expect($firstPageResponse)->toBeInstanceOf(JsonRpcResponse::class)
        ->and($firstPayload['id'])->toEqual(1)
        ->and($firstPayload['result']['tools'])->toHaveCount(10)
        ->and($firstPayload['result'])->toHaveKey('nextCursor')
        ->and($firstPayload['result']['nextCursor'])->not->toBeNull()
        ->and($firstPayload['result']['tools'][0]['name'])->toEqual('dummy-tool1')
        ->and($firstPayload['result']['tools'][9]['name'])->toEqual('dummy-tool10');

    $nextCursor = $firstPayload['result']['nextCursor'];

    $secondListToolsRequest = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'list-tools',
        'params' => ['cursor' => $nextCursor],
    ]);

    $secondPageResponse = $listTools->handle($secondListToolsRequest, $context);

    $secondPayload = $secondPageResponse->toArray();
    expect($secondPageResponse)->toBeInstanceOf(JsonRpcResponse::class)
        ->and($secondPayload['id'])->toEqual(2)
        ->and($secondPayload['result']['tools'])->toHaveCount(2)
        ->and($secondPayload['result'])->not->toHaveKey('nextCursor')
        ->and($secondPayload['result']['tools'][0]['name'])->toEqual('dummy-tool11')
        ->and($secondPayload['result']['tools'][1]['name'])->toEqual('dummy-tool12');
});

it('uses default per page when not provided', function (): void {
    $toolClasses = [];
    for ($i = 1; $i <= 12; $i++) {
        $toolClasses[] = "Tests\\Unit\\Methods\\DummyTool{$i}";
    }

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 7,
        tools: $toolClasses,
        resources: [],
        prompts: [],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => [/** no per_page */],
    ]);

    $listTools = new ListTools;
    $response = $listTools->handle($request, $context);

    $payload = $response->toArray();
    expect($payload['result']['tools'])->toHaveCount(7)
        ->and($payload['result'])->toHaveKey('nextCursor');
});

it('uses requested per page when valid', function (): void {
    $toolClasses = [];
    for ($i = 1; $i <= 12; $i++) {
        $toolClasses[] = "Tests\\Unit\\Methods\\DummyTool{$i}";
    }

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: $toolClasses,
        resources: [],
        prompts: [],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => ['per_page' => 5],
    ]);

    $listTools = new ListTools;
    $response = $listTools->handle($request, $context);

    $payload = $response->toArray();
    expect($payload['result']['tools'])->toHaveCount(5)
        ->and($payload['result'])->toHaveKey('nextCursor');
});

it('caps per page at max pagination length', function (): void {
    $toolClasses = [];
    for ($i = 1; $i <= 12; $i++) {
        $toolClasses[] = "Tests\\Unit\\Methods\\DummyTool{$i}";
    }

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 7,
        defaultPaginationLength: 7,
        tools: $toolClasses,
        resources: [],
        prompts: [],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => ['per_page' => 20],
    ]);

    $listTools = new ListTools;
    $response = $listTools->handle($request, $context);

    $payload = $response->toArray();
    expect($payload['result']['tools'])->toHaveCount(7)
        ->and($payload['result'])->toHaveKey('nextCursor');
});

it('respects per page when bigger than default', function (): void {
    $toolClasses = [];
    for ($i = 1; $i <= 12; $i++) {
        $toolClasses[] = "Tests\\Unit\\Methods\\DummyTool{$i}";
    }

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 15,
        defaultPaginationLength: 5,
        tools: $toolClasses,
        resources: [],
        prompts: [],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => ['per_page' => 8],
    ]);

    $listTools = new ListTools;
    $response = $listTools->handle($request, $context);

    $payload = $response->toArray();
    expect($payload['result']['tools'])->toHaveCount(8)
        ->and($payload['result'])->toHaveKey('nextCursor');
});

it('returns empty list when the single tool is not eligible for registration', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => [],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [new class extends SayHiTool
        {
            public function shouldRegister(): bool
            {
                return false;
            }
        }],
        resources: [],
        prompts: [],
    );

    $listTools = new ListTools;

    $response = $listTools->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'tools' => [],
        ]);
});

it('returns empty list when the single prompt is not eligible for registration via request', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => [
            'arguments' => ['register_tools' => false],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [new class extends SayHiTool
        {
            public function shouldRegister(Request $request): bool
            {
                return $request->get('register_tools', true);
            }
        }],
        resources: [],
        prompts: [],
    );

    $listTools = new ListTools;

    $this->instance('mcp.request', $request->toRequest());
    $response = $listTools->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'tools' => [],
        ]);
});

it('includes meta in tool response when tool has meta property', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => [],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [SayHiWithMetaTool::class],
        resources: [],
        prompts: [],
    );

    $listTools = new ListTools;

    $response = $listTools->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'tools' => [
                [
                    'name' => 'say-hi-with-meta-tool',
                    'title' => 'Say Hi With Meta Tool',
                    'description' => 'This tool says hello to a person with metadata',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'The name of the person to greet',
                            ],
                        ],
                        'required' => ['name'],
                    ],
                    'annotations' => (object) [],
                    '_meta' => [
                        'requestId' => 'abc-123',
                        'source' => 'tests/fixtures',
                    ],
                ],
            ],
        ]);
});
