<?php

use Laravel\Mcp\Client\ClientContext;
use Laravel\Mcp\Client\Methods\Initialize;
use Tests\Fixtures\FakeClientTransport;

it('sends initialize request with correct params', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => ['tools' => []],
                'serverInfo' => ['name' => 'test-server', 'version' => '1.0.0'],
            ],
        ]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');
    $handler = new Initialize;

    $handler->handle($context);

    $sent = $transport->sentMessages();
    expect($sent)->toHaveCount(1);

    $request = json_decode($sent[0], true);
    expect($request['method'])->toBe('initialize')
        ->and($request['params']['protocolVersion'])->toBe('2025-11-25')
        ->and($request['params']['clientInfo']['name'])->toBe('test-client')
        ->and($request['params']['clientInfo']['version'])->toBe('1.0.0');
});

it('sends notifications/initialized notification', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => [],
                'serverInfo' => ['name' => 'test-server', 'version' => '1.0.0'],
            ],
        ]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');
    $handler = new Initialize;

    $handler->handle($context);

    $notifications = $transport->notifications();
    expect($notifications)->toHaveCount(1);

    $notification = json_decode($notifications[0], true);
    expect($notification['method'])->toBe('notifications/initialized');
});

it('returns result with serverInfo and capabilities', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => ['tools' => [], 'prompts' => []],
                'serverInfo' => ['name' => 'my-server', 'version' => '2.0.0'],
            ],
        ]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');
    $handler = new Initialize;

    $result = $handler->handle($context);

    expect($result)->toHaveKey('serverInfo')
        ->and($result['serverInfo'])->toBe(['name' => 'my-server', 'version' => '2.0.0'])
        ->and($result['capabilities'])->toBe(['tools' => [], 'prompts' => []]);
});
