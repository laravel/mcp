<?php

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Schema\Icon;
use Laravel\Mcp\Schema\Implementation;
use Laravel\Mcp\Server\Methods\Initialize;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

it('returns a valid initialize response', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: ['listChanged' => false],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    $method = new Initialize;

    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'protocolVersion' => '2025-03-26',
            'capabilities' => ['listChanged' => false],
            'serverInfo' => [
                'name' => 'Test Server',
                'version' => '1.0.0',
            ],
        ]);
});

it('throws exception for unsupported protocol version', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Unsupported protocol version');
    $this->expectExceptionCode(-32602);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2024-11-05',
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    $method = new Initialize;

    try {
        $method->handle($request, $context);
    } catch (JsonRpcException $jsonRpcException) {
        $error = $jsonRpcException->toJsonRpcResponse()->toArray();

        expect($error)->toEqual([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32602,
                'message' => 'Unsupported protocol version',
                'data' => [
                    'supported' => ['2025-03-26'],
                    'requested' => '2024-11-05',
                ],
            ],
        ]);

        throw $jsonRpcException;
    }
});

it('includes icons, description, and websiteUrl in serverInfo when negotiated version supports them', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => ['protocolVersion' => '2025-11-25'],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-11-25'],
        serverCapabilities: ['listChanged' => false],
        implementation: new Implementation(
            name: 'Test Server',
            version: '1.0.0',
            description: 'A test server',
            icons: [
                new Icon('https://example.com/server.png', mimeType: 'image/png', sizes: ['48x48']),
                new Icon('https://example.com/server-dark.svg', mimeType: 'image/svg+xml', theme: 'dark'),
            ],
            websiteUrl: 'https://example.com',
        ),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    $payload = (new Initialize)->handle($request, $context)->toArray();

    expect($payload['result']['serverInfo'])->toEqual([
        'name' => 'Test Server',
        'version' => '1.0.0',
        'description' => 'A test server',
        'icons' => [
            ['src' => 'https://example.com/server.png', 'mimeType' => 'image/png', 'sizes' => ['48x48']],
            ['src' => 'https://example.com/server-dark.svg', 'mimeType' => 'image/svg+xml', 'theme' => 'dark'],
        ],
        'websiteUrl' => 'https://example.com',
    ]);
});

it('strips icons, description, and websiteUrl when negotiated version predates 2025-11-25', function (string $oldVersion): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => ['protocolVersion' => $oldVersion],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: [$oldVersion],
        serverCapabilities: ['listChanged' => false],
        implementation: new Implementation(
            name: 'Test Server',
            version: '1.0.0',
            description: 'A test server',
            icons: [new Icon('https://example.com/server.png', mimeType: 'image/png')],
            websiteUrl: 'https://example.com',
        ),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    $payload = (new Initialize)->handle($request, $context)->toArray();

    expect($payload['result']['serverInfo'])
        ->toHaveKey('name', 'Test Server')
        ->toHaveKey('version', '1.0.0')
        ->not->toHaveKey('icons')
        ->not->toHaveKey('description')
        ->not->toHaveKey('websiteUrl');
})->with(['2024-11-05', '2025-03-26', '2025-06-18']);

it('uses requested protocol version if supported', function (): void {
    $requestedVersion = '2024-11-05';
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => $requestedVersion,
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26', '2024-11-05'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    $method = new Initialize;
    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1)
        ->and($payload['result']['protocolVersion'])->toEqual($requestedVersion);
});
