<?php

use Laravel\Mcp\Client\ClientContext;
use Laravel\Mcp\Client\Methods\Ping;
use Tests\Fixtures\FakeClientTransport;

it('sends ping request', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [],
        ]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');
    $handler = new Ping;

    $handler->handle($context);

    $sent = $transport->sentMessages();
    $request = json_decode($sent[0], true);

    expect($request['method'])->toBe('ping');
});

it('returns empty result', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [],
        ]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');
    $handler = new Ping;

    $result = $handler->handle($context);

    expect($result)->toBe([]);
});
