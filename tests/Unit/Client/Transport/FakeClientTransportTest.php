<?php

use Laravel\Mcp\Client\Exceptions\ConnectionException;
use Tests\Fixtures\FakeClientTransport;

it('records sent messages', function (): void {
    $transport = new FakeClientTransport(['response-1', 'response-2']);
    $transport->connect();

    $response = $transport->send('message-1');

    expect($response)->toBe('response-1')
        ->and($transport->sentMessages())->toBe(['message-1']);
});

it('records notifications', function (): void {
    $transport = new FakeClientTransport;
    $transport->connect();

    $transport->notify('notification-1');

    expect($transport->notifications())->toBe(['notification-1']);
});

it('returns queued responses in order', function (): void {
    $transport = new FakeClientTransport(['first', 'second']);
    $transport->connect();

    expect($transport->send('a'))->toBe('first')
        ->and($transport->send('b'))->toBe('second')
        ->and($transport->send('c'))->toBe('{}');
});

it('throws when sending while disconnected', function (): void {
    $transport = new FakeClientTransport(['response']);

    $transport->send('message');
})->throws(ConnectionException::class, 'Not connected.');

it('throws when notifying while disconnected', function (): void {
    $transport = new FakeClientTransport;

    $transport->notify('notification');
})->throws(ConnectionException::class, 'Not connected.');

it('tracks connection state', function (): void {
    $transport = new FakeClientTransport;

    expect($transport->isConnected())->toBeFalse();

    $transport->connect();
    expect($transport->isConnected())->toBeTrue();

    $transport->disconnect();
    expect($transport->isConnected())->toBeFalse();
});

it('queues responses dynamically', function (): void {
    $transport = new FakeClientTransport;
    $transport->connect();

    $transport->queueResponse('dynamic-response');

    expect($transport->send('message'))->toBe('dynamic-response');
});
