<?php

use Laravel\Mcp\Client\ClientContext;
use Laravel\Mcp\Client\Methods\ListTools;
use Tests\Fixtures\FakeClientTransport;

it('returns tool definitions from result', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'tools' => [
                    ['name' => 'say-hi', 'description' => 'Says hello', 'inputSchema' => ['type' => 'object']],
                    ['name' => 'ping', 'description' => 'Pings', 'inputSchema' => ['type' => 'object']],
                ],
            ],
        ]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');
    $handler = new ListTools;

    $result = $handler->handle($context);

    expect($result)->toHaveKey('tools')
        ->and($result['tools'])->toHaveCount(2)
        ->and($result['tools'][0]['name'])->toBe('say-hi')
        ->and($result['tools'][1]['name'])->toBe('ping');
});

it('passes cursor params to request', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'tools' => [
                    ['name' => 'tool-2', 'description' => 'Second page'],
                ],
            ],
        ]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');
    $handler = new ListTools;

    $handler->handle($context, ['cursor' => 'cursor-abc']);

    $sent = $transport->sentMessages();
    $request = json_decode($sent[0], true);

    expect($request['method'])->toBe('tools/list')
        ->and($request['params']['cursor'])->toBe('cursor-abc');
});

it('handles empty tools list', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'tools' => [],
            ],
        ]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');
    $handler = new ListTools;

    $result = $handler->handle($context);

    expect($result['tools'])->toBe([]);
});
