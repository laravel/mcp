<?php

declare(strict_types=1);

use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\ExampleServer;

function listenMessages(array $notifications): array
{
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    ($transport->handler)((string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'subscriptions/listen',
        'params' => [
            '_meta' => protocolMeta(),
            'notifications' => $notifications,
        ],
    ]));

    return array_map(fn (string $sent): array => json_decode($sent, true), $transport->sent);
}

it('acknowledges an empty filter first and carries the subscription id', function (): void {
    $messages = listenMessages(['toolsListChanged' => true]);

    expect($messages[0]['method'])->toBe('notifications/subscriptions/acknowledged');
    expect($messages[0]['params']['_meta']['io.modelcontextprotocol/subscriptionId'])->toBe(7);
    expect($messages[0]['params']['notifications'])->toBe([]);
});

it('closes the subscription immediately with an empty result', function (): void {
    $messages = listenMessages(['toolsListChanged' => true, 'resourceSubscriptions' => ['file://a']]);

    expect($messages)->toHaveCount(2);

    $last = end($messages);

    expect($last['id'])->toBe(7);
    expect($last['result']['_meta']['io.modelcontextprotocol/subscriptionId'])->toBe(7);
});
