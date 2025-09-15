<?php

use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\Initialize;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

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
        serverName: 'Test Server',
        serverVersion: '1.0.0',
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
        serverName: 'Test Server',
        serverVersion: '1.0.0',
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
        serverName: 'Test Server',
        serverVersion: '1.0.0',
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
