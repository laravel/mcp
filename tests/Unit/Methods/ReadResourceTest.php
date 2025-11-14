<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\ReadResource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Tests\Fixtures\ResourceWithResultMetaResource;

it('returns a valid resource result', function (): void {
    $resource = $this->makeResource('resource-content');
    $readResource = new ReadResource;
    $context = $this->getServerContext();
    $context = $this->getServerContext([
        'resources' => [
            $resource,
        ],
    ]);
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/read', params: ['uri' => $resource->uri()]);
    $resourceResult = $readResource->handle($jsonRpcRequest, $context);

    $this->assertPartialMethodResult([
        'contents' => [
            [
                'text' => 'resource-content',
            ],
        ],
    ], $resourceResult);
});
it('returns a valid resource result for blob resources', function (): void {
    $resource = $this->makeBinaryResource(__DIR__.'/../../Fixtures/binary.png');
    $readResource = new ReadResource;
    $context = $this->getServerContext();
    $context = $this->getServerContext([
        'resources' => [
            $resource,
        ],
    ]);
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/read', params: ['uri' => $resource->uri()]);
    $resourceResult = $readResource->handle($jsonRpcRequest, $context);

    $this->assertPartialMethodResult([
        'contents' => [
            [
                'blob' => base64_encode(file_get_contents(__DIR__.'/../../Fixtures/binary.png')),
            ],
        ],
    ], $resourceResult);
});

it('throws error when uri is missing', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Missing [uri] parameter.');

    $readResource = new ReadResource;
    $context = $this->getServerContext();

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: [] // intentionally omitting 'uri'
    );

    $response = $readResource->handle($jsonRpcRequest, $context);

});

it('throws exception when resource is not found', function (): void {
    $this->expectException(JsonRpcException::class);

    $readResource = new ReadResource;
    $context = $this->getServerContext();

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://resources/non-existent']
    );

    $readResource->handle($jsonRpcRequest, $context);
});

it('returns resource result with result-level meta when using ResponseFactory', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/read',
        'params' => [
            'uri' => 'file://resources/with-result-meta.txt',
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
        resources: [ResourceWithResultMetaResource::class],
        prompts: [],
    );

    $method = new ReadResource;

    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);

    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload)->toMatchArray([
            'result' => [
                '_meta' => [
                    'last_modified' => '2025-01-01',
                    'version' => '1.0',
                ],
                'contents' => [
                    [
                        'text' => 'Resource content with result meta',
                        'uri' => 'file://resources/with-result-meta.txt',
                        'name' => 'resource-with-result-meta-resource',
                        'title' => 'Resource With Result Meta Resource',
                        'mimeType' => 'text/plain',
                    ],
                ],
            ],
        ]);
});
