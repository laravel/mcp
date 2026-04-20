<?php

use Laravel\Mcp\Client\ClientContext;
use Laravel\Mcp\Client\Exceptions\ClientException;
use Tests\Fixtures\FakeClientTransport;

it('sends request with incrementing ids', function (): void {
    $transport = new FakeClientTransport([
        json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['data' => 'first']]),
        json_encode(['jsonrpc' => '2.0', 'id' => 2, 'result' => ['data' => 'second']]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');

    $context->sendRequest('tools/list');
    $context->sendRequest('tools/call', ['name' => 'test']);

    $sent = $transport->sentMessages();
    expect($sent)->toHaveCount(2);

    $first = json_decode($sent[0], true);
    $second = json_decode($sent[1], true);

    expect($first['id'])->toBe(1)
        ->and($second['id'])->toBe(2);
});

it('throws client exception on error response', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['code' => -32602, 'message' => 'Invalid params'],
        ]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');

    $context->sendRequest('initialize');
})->throws(ClientException::class, 'Invalid params');

it('sends notifications via transport', function (): void {
    $transport = new FakeClientTransport;
    $transport->connect();

    $context = new ClientContext($transport, 'test-client');

    $context->notify('notifications/initialized');

    $notifications = $transport->notifications();
    expect($notifications)->toHaveCount(1);

    $notification = json_decode($notifications[0], true);
    expect($notification['method'])->toBe('notifications/initialized')
        ->and($notification['jsonrpc'])->toBe('2.0');
});

it('sends notifications with params', function (): void {
    $transport = new FakeClientTransport;
    $transport->connect();

    $context = new ClientContext($transport, 'test-client');

    $context->notify('notifications/progress', ['token' => 'abc', 'progress' => 50]);

    $notifications = $transport->notifications();
    $notification = json_decode($notifications[0], true);

    expect($notification['params'])->toBe(['token' => 'abc', 'progress' => 50]);
});

it('resets request id counter', function (): void {
    $transport = new FakeClientTransport([
        json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => []]),
        json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => []]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');

    $context->sendRequest('initialize');

    $first = json_decode($transport->sentMessages()[0], true);
    expect($first['id'])->toBe(1);

    $context->resetRequestId();

    $context->sendRequest('initialize');

    $second = json_decode($transport->sentMessages()[1], true);
    expect($second['id'])->toBe(1);
});
