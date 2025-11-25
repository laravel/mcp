<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Methods\ListResources;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ResourceTemplate;
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

it('excludes resource templates from list', function (): void {
    $template = new class extends ResourceTemplate
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

    $template = new class extends ResourceTemplate
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

it('returns empty list when only templates are registered', function (): void {
    $template1 = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('user');
        }
    };

    $template2 = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://posts/{postId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('post');
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
        resources: [$template1, $template2],
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

it('excludes template class strings from list', function (): void {
    $staticResource = $this->makeResource();

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [],
        resources: [Tests\Fixtures\ExampleResourceTemplate::class, $staticResource],
        prompts: [],
    );

    $request = new JsonRpcRequest(id: 1, method: 'resources/list', params: []);
    $listResources = new ListResources;
    $response = $listResources->handle($request, $context);

    $payload = $response->toArray();

    expect($payload['result']['resources'])->toHaveCount(1)
        ->and($payload['result']['resources'][0]['name'])->toBe($staticResource->name());
});
