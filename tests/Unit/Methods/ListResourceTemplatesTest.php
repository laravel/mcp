<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\SupportsUriTemplate;
use Laravel\Mcp\Server\Methods\ListResourceTemplates;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Support\UriTemplate;

it('lists only resource templates', function (): void {
    $staticResource = new class extends Resource
    {
        protected string $uri = 'file://logs/app.log';

        public function handle(): Response
        {
            return Response::text('log');
        }
    };

    $templateResource = new class extends Resource implements SupportsUriTemplate
    {
        protected string $mimeType = 'text/plain';

        protected string $description = 'User files';

        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}/files/{fileId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('file content');
        }
    };

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 15,
        tools: [],
        resources: [$staticResource, $templateResource],
        prompts: [],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/templates/list',
        'params' => [],
    ]);

    $handler = new ListResourceTemplates;
    $response = $handler->handle($request, $context);
    $payload = $response->toArray();

    expect($payload)->toHaveKey('result')
        ->and($payload['result'])->toHaveKey('resourceTemplates')
        ->and($payload['result']['resourceTemplates'])->toHaveCount(1)
        ->and($payload['result']['resourceTemplates'][0])->toHaveKey('uriTemplate')
        ->and($payload['result']['resourceTemplates'][0]['uriTemplate'])->toBe('file://users/{userId}/files/{fileId}');
});

it('returns an empty list when no templates exist', function (): void {
    $staticResource = new class extends Resource
    {
        protected string $uri = 'file://logs/app.log';

        public function handle(): Response
        {
            return Response::text('log');
        }
    };

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 15,
        tools: [],
        resources: [$staticResource],
        prompts: [],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/templates/list',
        'params' => [],
    ]);

    $handler = new ListResourceTemplates;
    $response = $handler->handle($request, $context);
    $payload = $response->toArray();

    expect($payload['result']['resourceTemplates'])->toBeArray()
        ->and($payload['result']['resourceTemplates'])->toBeEmpty();
});

it('includes template metadata in the listing', function (): void {
    $templateResource = new class extends Resource implements SupportsUriTemplate
    {
        protected string $name = 'user-file';

        protected string $title = 'User File Resource';

        protected string $description = 'Access user files by ID';

        protected string $mimeType = 'application/json';

        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{id}/data');
        }

        public function handle(Request $request): Response
        {
            return Response::text('data');
        }
    };

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 15,
        tools: [],
        resources: [$templateResource],
        prompts: [],
    );

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/templates/list',
        'params' => [],
    ]);

    $handler = new ListResourceTemplates;
    $response = $handler->handle($request, $context);
    $payload = $response->toArray();

    $template = $payload['result']['resourceTemplates'][0];

    expect($template['name'])->toBe('user-file')
        ->and($template['title'])->toBe('User File Resource')
        ->and($template['description'])->toBe('Access user files by ID')
        ->and($template['uriTemplate'])->toBe('file://users/{id}/data')
        ->and($template['mimeType'])->toBe('application/json');
});
