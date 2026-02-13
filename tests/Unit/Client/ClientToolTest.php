<?php

use Laravel\Mcp\Client\Client;
use Laravel\Mcp\Client\ClientTool;
use Tests\Fixtures\FakeClientTransport;

it('creates from array definition', function (): void {
    $transport = new FakeClientTransport;
    $client = new Client($transport, 'test');

    $tool = ClientTool::fromArray([
        'name' => 'say-hi',
        'description' => 'Says hello',
        'title' => 'Say Hi',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ],
    ], $client);

    expect($tool->name())->toBe('say-hi')
        ->and($tool->description())->toBe('Says hello')
        ->and($tool->title())->toBe('Say Hi');
});

it('converts to array with remote schema', function (): void {
    $transport = new FakeClientTransport;
    $client = new Client($transport, 'test');

    $inputSchema = [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'The name'],
        ],
        'required' => ['name'],
    ];

    $tool = ClientTool::fromArray([
        'name' => 'say-hi',
        'description' => 'Says hello',
        'title' => 'Say Hi',
        'inputSchema' => $inputSchema,
    ], $client);

    $array = $tool->toArray();

    expect($array['name'])->toBe('say-hi')
        ->and($array['description'])->toBe('Says hello')
        ->and($array['inputSchema'])->toBe($inputSchema);
});

it('handles request by proxying to client', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => [],
                'serverInfo' => ['name' => 'test', 'version' => '1.0.0'],
            ],
        ]),
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => [
                'content' => [['type' => 'text', 'text' => 'Hello, World!']],
                'isError' => false,
            ],
        ]),
    ]);

    $client = new Client($transport, 'test');
    $client->connect();

    $tool = ClientTool::fromArray([
        'name' => 'say-hi',
        'description' => 'Says hello',
    ], $client);

    $request = new \Laravel\Mcp\Request(['name' => 'World']);
    $response = $tool->handle($request);

    expect($response->content())->toBeInstanceOf(\Laravel\Mcp\Server\Content\Text::class);
});

it('exposes remote input schema', function (): void {
    $transport = new FakeClientTransport;
    $client = new Client($transport, 'test');

    $schema = ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]];

    $tool = ClientTool::fromArray([
        'name' => 'my-tool',
        'inputSchema' => $schema,
    ], $client);

    expect($tool->remoteInputSchema())->toBe($schema);
});

it('defaults empty fields gracefully', function (): void {
    $transport = new FakeClientTransport;
    $client = new Client($transport, 'test');

    $tool = ClientTool::fromArray([], $client);

    expect($tool->name())->toBe('client-tool')
        ->and($tool->description())->toBe('Client Tool')
        ->and($tool->remoteInputSchema())->toBe([]);
});
