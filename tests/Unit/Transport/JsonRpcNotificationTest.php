<?php

declare(strict_types=1);

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Transport\JsonRpcNotification;

it('can create a notification message from valid json', function (): void {
    $request = JsonRpcNotification::from([
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
    ]);

    expect($request)->toBeInstanceOf(JsonRpcNotification::class)
        ->and($request->method)->toEqual('notifications/initialized')
        ->and($request->params)->toEqual([]);
});

it('throws exception for missing jsonrpc version in notification', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: Invalid JSON-RPC version. Must be "2.0".');
    $this->expectExceptionCode(-32600);

    JsonRpcNotification::from([
        'method' => 'notifications/ping',
    ]);
});

it('throws exception for incorrect jsonrpc version in notification', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: Invalid JSON-RPC version. Must be "2.0".');
    $this->expectExceptionCode(-32600);

    JsonRpcNotification::from([
        'jsonrpc' => '1.0',
        'method' => 'notifications/ping',
    ]);
});

it('throws exception for missing method in notification', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: Invalid or missing "method". Must be a string.');
    $this->expectExceptionCode(-32600);

    JsonRpcNotification::from([
        'jsonrpc' => '2.0',
    ]);
});

it('throws exception for non string method in notification', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: Invalid or missing "method". Must be a string.');
    $this->expectExceptionCode(-32600);

    JsonRpcNotification::from([
        'jsonrpc' => '2.0',
        'method' => 123,
    ]);
});

it('serializes to an array with params when present', function (): void {
    $notification = new JsonRpcNotification('notifications/progress', ['progress' => 50]);

    expect($notification->toArray())->toEqual([
        'jsonrpc' => '2.0',
        'method' => 'notifications/progress',
        'params' => ['progress' => 50],
    ]);
});

it('omits empty params when serializing', function (): void {
    $notification = new JsonRpcNotification('notifications/initialized', []);

    expect($notification->toArray())->toEqual([
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
    ]);
});

it('serializes to json without an id', function (): void {
    $notification = new JsonRpcNotification('notifications/initialized', []);

    expect($notification->toJson())->toEqual('{"jsonrpc":"2.0","method":"notifications\/initialized"}');
});
