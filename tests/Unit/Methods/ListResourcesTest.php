<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Methods\ListResources;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

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

it('returns empty list when the single tool is not eligible for registration', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-resources',
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
        tools: [],
        resources: [new class extends Resource
        {
            public function read(): string
            {
                return 'foo';
            }

            public function shouldRegister(): bool
            {
                return false;
            }
        }],
        prompts: [],
    );

    $listResources = new ListResources;

    $response = $listResources->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'resources' => [],
        ]);
});

it('returns empty list when the single prompt is not eligible for registration via request', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-tools',
        'params' => [
            'arguments' => ['register_resources' => false],
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
        tools: [],
        resources: [new class extends Resource
        {
            public function read(): string
            {
                return 'foo';
            }

            public function shouldRegister(Request $request): bool
            {
                return $request->get('register_resources', true);
            }
        }],
        prompts: [],
    );

    $listResources = new ListResources;

    $response = $listResources->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'resources' => [],
        ]);
});
