<?php

use Laravel\Mcp\Client\Client;
use Laravel\Mcp\Client\ClientTool;
use Laravel\Mcp\Client\Exceptions\ClientException;
use Tests\Fixtures\FakeClientTransport;

it('connects and initializes', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => ['tools' => []],
                'serverInfo' => ['name' => 'test', 'version' => '1.0.0'],
            ],
        ]),
    ]);

    $client = new Client($transport, 'test-client');
    $client->connect();

    expect($client->isConnected())->toBeTrue()
        ->and($client->serverInfo())->toBe(['name' => 'test', 'version' => '1.0.0'])
        ->and($client->serverCapabilities())->toBe(['tools' => []]);

    $sent = $transport->sentMessages();
    expect($sent)->toHaveCount(1);

    $initRequest = json_decode($sent[0], true);
    expect($initRequest['method'])->toBe('initialize')
        ->and($initRequest['params']['clientInfo']['name'])->toBe('test-client');

    $notifications = $transport->notifications();
    expect($notifications)->toHaveCount(1);

    $initNotification = json_decode($notifications[0], true);
    expect($initNotification['method'])->toBe('notifications/initialized');
});

it('lists tools', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => ['tools' => []],
                'serverInfo' => ['name' => 'test', 'version' => '1.0.0'],
            ],
        ]),
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => [
                'tools' => [
                    ['name' => 'say-hi', 'description' => 'Says hello', 'inputSchema' => ['type' => 'object']],
                    ['name' => 'ping', 'description' => 'Pings', 'inputSchema' => ['type' => 'object']],
                ],
            ],
        ]),
    ]);

    $client = new Client($transport, 'test-client');
    $client->connect();

    $tools = $client->tools();

    expect($tools)->toHaveCount(2)
        ->and($tools->first())->toBeInstanceOf(ClientTool::class)
        ->and($tools->first()->name())->toBe('say-hi')
        ->and($tools->first()->description())->toBe('Says hello')
        ->and($tools->last()->name())->toBe('ping');
});

it('calls a tool', function (): void {
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

    $client = new Client($transport, 'test-client');
    $client->connect();

    $result = $client->callTool('say-hi', ['name' => 'World']);

    expect($result)->toHaveKey('content')
        ->and($result['content'][0]['text'])->toBe('Hello, World!');

    $sent = $transport->sentMessages();
    $callRequest = json_decode($sent[1], true);
    expect($callRequest['method'])->toBe('tools/call')
        ->and($callRequest['params']['name'])->toBe('say-hi')
        ->and($callRequest['params']['arguments'])->toBe(['name' => 'World']);
});

it('throws exception on error response', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32602,
                'message' => 'Tool not found.',
            ],
        ]),
    ]);

    $client = new Client($transport, 'test-client');

    $client->connect();
})->throws(ClientException::class, 'Tool not found.');

it('disconnects and resets state', function (): void {
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
    ]);

    $client = new Client($transport, 'test-client');
    $client->connect();

    expect($client->isConnected())->toBeTrue();

    $client->disconnect();

    expect($client->isConnected())->toBeFalse()
        ->and($client->serverInfo())->toBeNull()
        ->and($client->serverCapabilities())->toBeNull();
});

it('reports not connected before connect is called', function (): void {
    $transport = new FakeClientTransport;

    $client = new Client($transport, 'test-client');

    expect($client->isConnected())->toBeFalse();
});

it('caches tools when cache ttl is set', function (): void {
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
                'tools' => [
                    ['name' => 'cached-tool', 'description' => 'A cached tool'],
                ],
            ],
        ]),
    ]);

    $client = new Client($transport, 'cache-test', cacheTtl: 300);
    $client->connect();

    $tools = $client->tools();
    expect($tools)->toHaveCount(1)
        ->and($tools->first()->name())->toBe('cached-tool');

    $toolsAgain = $client->tools();
    expect($toolsAgain)->toHaveCount(1);

    expect($transport->sentMessages())->toHaveCount(2);

    $client->clearCache();
});

it('paginates tools list using cursor', function (): void {
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
                'tools' => [
                    ['name' => 'tool-1', 'description' => 'First tool'],
                ],
                'nextCursor' => 'cursor-abc',
            ],
        ]),
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 3,
            'result' => [
                'tools' => [
                    ['name' => 'tool-2', 'description' => 'Second tool'],
                ],
            ],
        ]),
    ]);

    $client = new Client($transport, 'test-client');
    $client->connect();

    $tools = $client->tools();

    expect($tools)->toHaveCount(2)
        ->and($tools->first()->name())->toBe('tool-1')
        ->and($tools->last()->name())->toBe('tool-2');

    $sent = $transport->sentMessages();
    expect($sent)->toHaveCount(3);

    $secondRequest = json_decode($sent[1], true);
    expect($secondRequest['method'])->toBe('tools/list')
        ->and($secondRequest)->not->toHaveKey('params');

    $thirdRequest = json_decode($sent[2], true);
    expect($thirdRequest['method'])->toBe('tools/list')
        ->and($thirdRequest['params']['cursor'])->toBe('cursor-abc');
});

it('sends ping request', function (): void {
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
            'result' => [],
        ]),
    ]);

    $client = new Client($transport, 'test-client');
    $client->connect();

    $client->ping();

    $sent = $transport->sentMessages();
    expect($sent)->toHaveCount(2);

    $pingRequest = json_decode($sent[1], true);
    expect($pingRequest['method'])->toBe('ping');
});

it('sends capabilities during initialization', function (): void {
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
    ]);

    $client = new Client($transport, 'test-client', capabilities: ['sampling' => []]);
    $client->connect();

    $sent = $transport->sentMessages();
    $initRequest = json_decode($sent[0], true);
    expect($initRequest['params']['capabilities'])->toBe(['sampling' => []]);
});

it('sends empty capabilities object when none configured', function (): void {
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
    ]);

    $client = new Client($transport, 'test-client');
    $client->connect();

    $sent = $transport->sentMessages();
    $initRequest = json_decode($sent[0], true);
    expect($initRequest['params']['capabilities'])->toBeEmpty();
});

it('clears cache', function (): void {
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
    ]);

    $client = new Client($transport, 'clear-test', cacheTtl: 300);
    $client->connect();

    $client->clearCache();

    expect(true)->toBeTrue();
});
