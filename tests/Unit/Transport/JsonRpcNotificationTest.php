<?php

use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\JsonRpcNotification;

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

it('defaults params to empty array and supports getters in notification', function (): void {
    $notification = JsonRpcNotification::from([
        'jsonrpc' => '2.0',
        'method' => 'notifications/ready',
        // no params
    ]);

    expect($notification->params)->toEqual([])
        ->and($notification->get('missing', 'default'))->toEqual('default')
        ->and($notification->cursor())->toBeNull();

    $withCursor = JsonRpcNotification::from([
        'jsonrpc' => '2.0',
        'method' => 'notifications/update',
        'params' => ['cursor' => 'N-CUR-1', 'foo' => 'bar'],
    ]);

    expect($withCursor->cursor())->toEqual('N-CUR-1')
        ->and($withCursor->get('foo'))->toEqual('bar');
});
