<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\ReadResource;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;

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

it('returns a valid resource result from template', function (): void {
    $resource = $this->makeResource(overrides: ['uri' => 'file://resource/{type}']);
    $readResource = new ReadResource;
    $context = $this->getServerContext([
        'resources' => [
            $resource,
        ],
    ]);
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/read', params: ['uri' => 'file://resource/template']);
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
    $this->expectExceptionMessage('Resource [file://resources/non-existent] not found.');

    $readResource = new ReadResource;
    $context = $this->getServerContext();

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://resources/non-existent']
    );

    $response = $readResource->handle($jsonRpcRequest, $context);
});

it('throws exception when resource template is not found', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Resource [file://resource/template/extra] not found.');

    $resource = $this->makeResource(overrides: ['uri' => 'file://resource/{type}']);
    $readResource = new ReadResource;
    $context = $this->getServerContext([
        'resources' => [
            $resource,
        ],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://resource/template/extra']
    );

    $response = $readResource->handle($jsonRpcRequest, $context);
});
