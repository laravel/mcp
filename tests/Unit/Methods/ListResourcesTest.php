<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Annotations\Audience;
use Laravel\Mcp\Server\Annotations\LastModified;
use Laravel\Mcp\Server\Annotations\Priority;
use Laravel\Mcp\Server\Contracts\SupportsURITemplate;
use Laravel\Mcp\Server\Methods\ListResources;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\UriTemplate;

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
            public function handle(): string
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

    $this->instance('mcp.request', $request->toRequest());
    $response = $listResources->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'resources' => [],
        ]);
});

it('includes annotations when a resource has annotations', function (): void {
    $listResources = new ListResources;

    $resource = new #[Audience([Role::USER, Role::ASSISTANT])]
    #[Priority(0.8)]
    #[LastModified('2025-01-12T15:00:58Z')]
    class extends Resource
    {
        public function handle(): string
        {
            return 'test content';
        }
    };

    $context = $this->getServerContext([
        'resources' => [$resource],
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
                'annotations' => [
                    'audience' => ['user', 'assistant'],
                    'priority' => 0.8,
                    'lastModified' => '2025-01-12T15:00:58Z',
                ],
            ],
        ],
    ], $listResources->handle($jsonRpcRequest, $context));
});

it('excludes an annotation key when a resource has no annotations', function (): void {
    $listResources = new ListResources;

    $resource = new class extends Resource
    {
        public function handle(): string
        {
            return 'test content';
        }
    };

    $context = $this->getServerContext([
        'resources' => [$resource],
    ]);
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/list', params: []);

    $response = $listResources->handle($jsonRpcRequest, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();

    expect($payload['result']['resources'])->toHaveCount(1)
        ->and($payload['result']['resources'][0])->not->toHaveKey('annotations');
});

it('handles mixed resources with and without annotations', function (): void {
    $listResources = new ListResources;

    $annotatedResource = new #[Audience(Role::USER)]
    #[Priority(0.5)]
    class extends Resource
    {
        public function handle(): string
        {
            return 'annotated content';
        }
    };

    $plainResource = new class extends Resource
    {
        public function handle(): string
        {
            return 'plain content';
        }
    };

    $context = $this->getServerContext([
        'resources' => [$annotatedResource, $plainResource],
    ]);
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/list', params: []);

    $response = $listResources->handle($jsonRpcRequest, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();

    expect($payload['result']['resources'])->toHaveCount(2)
        ->and($payload['result']['resources'][0])
        ->toHaveKey('annotations')
        ->and($payload['result']['resources'][0]['annotations'])->toEqual([
            'audience' => ['user'],
            'priority' => 0.5,
        ])
        ->and($payload['result']['resources'][1])->not->toHaveKey('annotations');

});

it('excludes resource templates from list', function (): void {
    $template = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [],
        resources: [$template],
        prompts: [],
    );

    $request = new JsonRpcRequest(id: 1, method: 'resources/list', params: []);
    $listResources = new ListResources;
    $response = $listResources->handle($request, $context);

    $payload = $response->toArray();

    expect($payload['result'])->toEqual([
        'resources' => [],
    ]);
});

it('returns only static resources when both templates and static resources exist', function (): void {
    $staticResource = $this->makeResource();

    $template = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [],
        resources: [$staticResource, $template],
        prompts: [],
    );

    $request = new JsonRpcRequest(id: 1, method: 'resources/list', params: []);
    $listResources = new ListResources;
    $response = $listResources->handle($request, $context);

    $payload = $response->toArray();

    expect($payload['result']['resources'])->toHaveCount(1)
        ->and($payload['result']['resources'][0]['name'])->toBe($staticResource->name())
        ->and($payload['result']['resources'][0]['uri'])->toBe($staticResource->uri());
});
