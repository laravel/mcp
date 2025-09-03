<?php

use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Tests\Fixtures\ExampleTool;

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
                public function schema(): JsonSchema { return JsonSchema::object(); }
                public function handle(array \$arguments): ToolResult|Generator { return []; }
            }
        ");
    }
}

it('returns a valid list tools response', function () {
    $request = JsonRpcRequest::fromJson(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => [],
    ]));

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [ExampleTool::class],
        resources: [],
        prompts: [],
    );

    $listTools = new ListTools;

    $response = $listTools->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    expect($response->id)->toEqual(1);
    expect($response->result)->toEqual([
        'tools' => [
            [
                'name' => 'example-tool',
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
            ],
        ],
    ]);
});

it('handles pagination correctly', function () {
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

    $firstListToolsRequest = JsonRpcRequest::fromJson(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => [],
    ]));

    $firstPageResponse = $listTools->handle($firstListToolsRequest, $context);

    expect($firstPageResponse)->toBeInstanceOf(JsonRpcResponse::class);
    expect($firstPageResponse->id)->toEqual(1);
    expect($firstPageResponse->result['tools'])->toHaveCount(10);
    expect($firstPageResponse->result)->toHaveKey('nextCursor');
    expect($firstPageResponse->result['nextCursor'])->not->toBeNull();

    expect($firstPageResponse->result['tools'][0]['name'])->toEqual('dummy-tool1');

    expect($firstPageResponse->result['tools'][9]['name'])->toEqual('dummy-tool10');

    $nextCursor = $firstPageResponse->result['nextCursor'];

    $secondListToolsRequest = JsonRpcRequest::fromJson(json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'list-tools',
        'params' => ['cursor' => $nextCursor],
    ]));

    $secondPageResponse = $listTools->handle($secondListToolsRequest, $context);

    expect($secondPageResponse)->toBeInstanceOf(JsonRpcResponse::class);
    expect($secondPageResponse->id)->toEqual(2);
    expect($secondPageResponse->result['tools'])->toHaveCount(2);
    $this->assertArrayNotHasKey('nextCursor', $secondPageResponse->result);

    expect($secondPageResponse->result['tools'][0]['name'])->toEqual('dummy-tool11');

    expect($secondPageResponse->result['tools'][1]['name'])->toEqual('dummy-tool12');
});

it('uses default per page when not provided', function () {
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

    $request = JsonRpcRequest::fromJson(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => [/** no per_page */],
    ]));

    $listTools = new ListTools;
    $response = $listTools->handle($request, $context);

    expect($response->result['tools'])->toHaveCount(7);
    expect($response->result)->toHaveKey('nextCursor');
});

it('uses requested per page when valid', function () {
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

    $request = JsonRpcRequest::fromJson(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => ['per_page' => 5],
    ]));

    $listTools = new ListTools;
    $response = $listTools->handle($request, $context);

    expect($response->result['tools'])->toHaveCount(5);
    expect($response->result)->toHaveKey('nextCursor');
});

it('caps per page at max pagination length', function () {
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

    $request = JsonRpcRequest::fromJson(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => ['per_page' => 20],
    ]));

    $listTools = new ListTools;
    $response = $listTools->handle($request, $context);

    expect($response->result['tools'])->toHaveCount(7);
    expect($response->result)->toHaveKey('nextCursor');
});

it('respects per page when bigger than default', function () {
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

    $request = JsonRpcRequest::fromJson(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => ['per_page' => 8],
    ]));

    $listTools = new ListTools;
    $response = $listTools->handle($request, $context);

    expect($response->result['tools'])->toHaveCount(8);
    expect($response->result)->toHaveKey('nextCursor');
});
