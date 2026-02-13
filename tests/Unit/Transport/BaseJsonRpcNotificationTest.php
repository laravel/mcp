<?php

use Laravel\Mcp\Transport\JsonRpcNotification;

it('creates a notification with constructor', function (): void {
    $notification = new JsonRpcNotification('notifications/initialized', []);

    expect($notification->method)->toBe('notifications/initialized')
        ->and($notification->params)->toBe([]);
});

it('serializes to array', function (): void {
    $notification = new JsonRpcNotification('notifications/progress', ['progress' => 50]);

    expect($notification->toArray())->toBe([
        'jsonrpc' => '2.0',
        'method' => 'notifications/progress',
        'params' => ['progress' => 50],
    ]);
});

it('omits params when empty', function (): void {
    $notification = new JsonRpcNotification('notifications/initialized', []);

    expect($notification->toArray())->toBe([
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
    ]);
});

it('serializes to json', function (): void {
    $notification = new JsonRpcNotification('notifications/progress', ['progress' => 75]);

    $json = $notification->toJson();

    expect(json_decode($json, true))->toBe([
        'jsonrpc' => '2.0',
        'method' => 'notifications/progress',
        'params' => ['progress' => 75],
    ]);
});
