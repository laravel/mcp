<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Methods\ListResourceTemplates;
use Laravel\Mcp\Server\ResourceTemplate;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

it('returns a valid empty list resource templates response', function (): void {
    $listResourceTemplates = new ListResourceTemplates;
    $context = $this->getServerContext([
        'resourceTemplates' => [],
    ]);
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/templates/list', params: []);
    $result = $listResourceTemplates->handle($jsonRpcRequest, $context);

    $this->assertMethodResult([
        'resourceTemplates' => [],
    ], $result);
});

it('returns a valid populated list resource templates response', function (): void {
    $listResourceTemplates = new ListResourceTemplates;
    $resourceTemplate = $this->makeResourceTemplate();

    $context = $this->getServerContext([
        'resourceTemplates' => [
            $resourceTemplate,
        ],
    ]);
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/templates/list', params: []);

    $this->assertMethodResult([
        'resourceTemplates' => [
            [
                'name' => $resourceTemplate->name(),
                'title' => $resourceTemplate->title(),
                'description' => $resourceTemplate->description(),
                'uriTemplate' => $resourceTemplate->uriTemplate(),
                'mimeType' => $resourceTemplate->mimeType(),
            ],
        ],
    ], $listResourceTemplates->handle($jsonRpcRequest, $context));
});

it('returns empty list when the single resource template is not eligible for registration', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/templates/list',
        'params' => [],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-06-18'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [],
        resources: [],
        resourceTemplates: [new class extends ResourceTemplate
        {
            public function handle(): string
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

    $listResourceTemplates = new ListResourceTemplates;

    $response = $listResourceTemplates->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'resourceTemplates' => [],
        ]);
});

it('returns empty list when the single resource template is not eligible for registration via request', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/templates/list',
        'params' => [
            'arguments' => ['register_templates' => false],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-06-18'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [],
        resources: [],
        resourceTemplates: [new class extends ResourceTemplate
        {
            public function handle(): string
            {
                return 'foo';
            }

            public function shouldRegister(Request $request): bool
            {
                return $request->get('register_templates', true);
            }
        }],
        prompts: [],
    );

    $listResourceTemplates = new ListResourceTemplates;

    $this->instance('mcp.request', $request->toRequest());
    $response = $listResourceTemplates->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'resourceTemplates' => [],
        ]);
});

it('handles multiple resource templates', function (): void {
    $listResourceTemplates = new ListResourceTemplates;
    $template1 = $this->makeResourceTemplate('file://resources/user/{id}', 'User template');
    $template2 = $this->makeResourceTemplate('file://resources/post/{id}', 'Post template');

    $context = $this->getServerContext([
        'resourceTemplates' => [
            $template1,
            $template2,
        ],
    ]);
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/templates/list', params: []);

    $result = $listResourceTemplates->handle($jsonRpcRequest, $context);
    $payload = $result->toArray();

    expect($payload['result']['resourceTemplates'])->toHaveCount(2)
        ->and($payload['result']['resourceTemplates'][0]['uriTemplate'])->toBe('file://resources/user/{id}')
        ->and($payload['result']['resourceTemplates'][1]['uriTemplate'])->toBe('file://resources/post/{id}');
});
