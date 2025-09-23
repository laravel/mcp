<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\Methods\GetPrompt;
use Laravel\Mcp\Server\Methods\ListPrompts;
use Laravel\Mcp\Server\Methods\ListResources;
use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\Methods\ReadResource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Tests\Fixtures\RequiresAuthPrompt;
use Tests\Fixtures\RequiresAuthResource;
use Tests\Fixtures\RequiresAuthTool;
use Tests\Support\Fakes\FakeAuthManager;
use Tests\Support\Fakes\FakeUser;

it('filters unauthorized items from listing', function (): void {

    // No abilities / scopes
    $this->app->instance('auth', new FakeAuthManager(new FakeUser([], [])));

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test',
        serverVersion: '1.0.0',
        instructions: 'x',
        maxPaginationLength: 10,
        defaultPaginationLength: 10,
        tools: [RequiresAuthTool::class],
        resources: [RequiresAuthResource::class],
        prompts: [RequiresAuthPrompt::class],
    );

    // Tools
    $toolsResponse = (new ListTools)->handle(new JsonRpcRequest(id: 1, method: 'list-tools', params: []), $context)->toArray();
    expect($toolsResponse['result']['tools'])->toBe([]);

    // Resources
    $resourcesResponse = (new ListResources)->handle(new JsonRpcRequest(id: 2, method: 'resources/list', params: []), $context)->toArray();
    expect($resourcesResponse['result']['resources'])->toBe([]);

    // Prompts
    $promptsResponse = (new ListPrompts)->handle(new JsonRpcRequest(id: 3, method: 'list-prompts', params: []), $context)->toArray();
    expect($promptsResponse['result']['prompts'])->toBe([]);
});

it('includes authorized items when user has required ability and scope', function (): void {

    $this->app->instance('auth', new FakeAuthManager(new FakeUser(
        abilities: ['tools.update', 'resources.read', 'prompts.read'],
        scopes: ['tools:read', 'resources:read', 'prompts:read'],
    )));

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test',
        serverVersion: '1.0.0',
        instructions: 'x',
        maxPaginationLength: 10,
        defaultPaginationLength: 10,
        tools: [RequiresAuthTool::class],
        resources: [RequiresAuthResource::class],
        prompts: [RequiresAuthPrompt::class],
    );

    $toolsResponse = (new ListTools)->handle(new JsonRpcRequest(id: 1, method: 'list-tools', params: []), $context)->toArray();
    expect($toolsResponse['result']['tools'])->toHaveCount(1);

    $resourcesResponse = (new ListResources)->handle(new JsonRpcRequest(id: 2, method: 'resources/list', params: []), $context)->toArray();
    expect($resourcesResponse['result']['resources'])->toHaveCount(1);

    $promptsResponse = (new ListPrompts)->handle(new JsonRpcRequest(id: 3, method: 'list-prompts', params: []), $context)->toArray();
    expect($promptsResponse['result']['prompts'])->toHaveCount(1);
});

it('rejects unauthorized invocation with JsonRpcException', function (): void {

    // User lacks required ability / scope
    $this->app->instance('auth', new FakeAuthManager(new FakeUser([], [])));

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test',
        serverVersion: '1.0.0',
        instructions: 'x',
        maxPaginationLength: 10,
        defaultPaginationLength: 10,
        tools: [RequiresAuthTool::class],
        resources: [RequiresAuthResource::class],
        prompts: [RequiresAuthPrompt::class],
    );

    // Call tool
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 99,
        'method' => 'tools/call',
        'params' => [
            'name' => 'requires-auth-tool',
            'arguments' => [],
        ],
    ]);

    $this->expectException(\Laravel\Mcp\Server\Exceptions\JsonRpcException::class);
    $this->expectExceptionMessage('Unauthorized');

    (new CallTool)->handle($request, $context);
});

it('allows invocation when authorized', function (): void {

    // Has both ability and scope
    $this->app->instance('auth', new FakeAuthManager(new FakeUser(['tools.update'], ['tools:read'])));

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test',
        serverVersion: '1.0.0',
        instructions: 'x',
        maxPaginationLength: 10,
        defaultPaginationLength: 10,
        tools: [RequiresAuthTool::class],
        resources: [],
        prompts: [],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 100,
        'method' => 'tools/call',
        'params' => [
            'name' => 'requires-auth-tool',
            'arguments' => [],
        ],
    ]);

    $response = (new CallTool)->handle($request, $context);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(100);
});

it('rejects unauthorized prompt invocation with JsonRpcException', function (): void {
    // user has no abilities/scopes
    $this->app->instance('auth', new FakeAuthManager(new FakeUser([], [])));

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test',
        serverVersion: '1.0.0',
        instructions: 'x',
        maxPaginationLength: 10,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [RequiresAuthPrompt::class],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 101,
        'method' => 'prompts/get',
        'params' => [
            'name' => 'requires-auth-prompt',
            'arguments' => [],
        ],
    ]);

    $this->expectException(\Laravel\Mcp\Server\Exceptions\JsonRpcException::class);
    $this->expectExceptionMessage('Unauthorized');

    (new GetPrompt)->handle($request, $context);
});

it('allows prompt invocation when authorized', function (): void {
    $this->app->instance('auth', new FakeAuthManager(new FakeUser(['prompts.read'], ['prompts:read'])));

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test',
        serverVersion: '1.0.0',
        instructions: 'x',
        maxPaginationLength: 10,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [RequiresAuthPrompt::class],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 102,
        'method' => 'prompts/get',
        'params' => [
            'name' => 'requires-auth-prompt',
            'arguments' => [],
        ],
    ]);

    $response = (new GetPrompt)->handle($request, $context);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(102);
});

it('rejects unauthorized resource read with JsonRpcException', function (): void {
    $this->app->instance('auth', new FakeAuthManager(new FakeUser([], [])));

    $resource = new \Tests\Fixtures\RequiresAuthResource;
    $uri = $resource->uri();

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test',
        serverVersion: '1.0.0',
        instructions: 'x',
        maxPaginationLength: 10,
        defaultPaginationLength: 10,
        tools: [],
        resources: [\Tests\Fixtures\RequiresAuthResource::class],
        prompts: [],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 103,
        'method' => 'resources/read',
        'params' => [
            'uri' => $uri,
        ],
    ]);

    $this->expectException(\Laravel\Mcp\Server\Exceptions\JsonRpcException::class);
    $this->expectExceptionMessage('Unauthorized');

    (new ReadResource)->handle($request, $context);
});

it('allows resource read when authorized', function (): void {
    $this->app->instance('auth', new FakeAuthManager(new FakeUser(['resources.read'], ['resources:read'])));

    $resource = new \Tests\Fixtures\RequiresAuthResource;
    $uri = $resource->uri();

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test',
        serverVersion: '1.0.0',
        instructions: 'x',
        maxPaginationLength: 10,
        defaultPaginationLength: 10,
        tools: [],
        resources: [\Tests\Fixtures\RequiresAuthResource::class],
        prompts: [],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 104,
        'method' => 'resources/read',
        'params' => [
            'uri' => $uri,
        ],
    ]);

    $response = (new ReadResource)->handle($request, $context);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(104);
});
