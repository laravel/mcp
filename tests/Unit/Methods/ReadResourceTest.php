<?php

declare(strict_types=1);

use Illuminate\Support\ItemNotFoundException;
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
it('throws exception when uri is missing', function (): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Missing required parameter: uri');

    $readResource = new ReadResource;
    $context = $this->getServerContext();

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: [] // intentionally omitting 'uri'
    );

    $readResource->handle($jsonRpcRequest, $context);
});
it('throws exception when resource is not found', function (): void {
    $this->expectException(ItemNotFoundException::class);

    $readResource = new ReadResource;
    $context = $this->getServerContext();

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://resources/non-existent']
    );

    $readResource->handle($jsonRpcRequest, $context);
});
