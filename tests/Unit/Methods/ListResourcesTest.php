<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Methods\ListResources;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;

it('returns a valid empty list resources response', function (): void {
    $listResources = new ListResources;
    $context = $this->getServerContext();
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/list', params: []);
    $result = $listResources->handle($jsonRpcRequest, $context);

    $this->assertMethodResult([
        'resources' => [],
    ], $result);
});
it('returns a valid populated list resources response', function (): void {
    $listResources = new ListResources;
    $resource = $this->makeResource();

    $context = $this->getServerContext([
        'resources' => [
            $resource,
        ],
    ]);
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/list', params: []);

    $this->assertMethodResult([
        'resources' => [
            [
                'name' => $resource->name(),
                'title' => $resource->title(),
                'description' => $resource->description(),
                'uri' => $resource->uri(),
                'mimeType' => $resource->mimeType(),
            ],
        ],
    ], $listResources->handle($jsonRpcRequest, $context));
});
