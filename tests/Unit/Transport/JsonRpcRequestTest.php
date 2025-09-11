<?php

use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\JsonRpcNotification;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;

it('can create a message from valid json', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'echo',
            'arguments' => [
                'message' => 'Hello, world!',
            ],
        ],
    ]);

    expect($request)->toBeInstanceOf(JsonRpcRequest::class)
        ->and($request->id)->toEqual(1)
        ->and($request->method)->toEqual('tools/call')
        ->and($request->params)->toEqual(['name' => 'echo', 'arguments' => ['message' => 'Hello, world!']]);
});

it('can create a notification message from valid json', function (): void {
    $json = '{"jsonrpc": "2.0", "method": "notifications/initialized"}';
    $request = JsonRpcNotification::from([
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
    ]);

    expect($request)->toBeInstanceOf(JsonRpcNotification::class)
        ->and($request->method)->toEqual('notifications/initialized')
        ->and($request->params)->toEqual([]);
});

it('throws exception for missing jsonrpc version', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: The [jsonrpc] member must be exactly [2.0]');
    $this->expectExceptionCode(-32600);

    JsonRpcRequest::from([
        'id' => 1,
        'method' => 'initialize',
    ]);
});

it('throws exception for incorrect jsonrpc version', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: The [jsonrpc] member must be exactly [2.0].');
    $this->expectExceptionCode(-32600);

    JsonRpcRequest::from([
        'jsonrpc' => '1.0',
        'id' => 1,
        'method' => 'initialize',
    ]);
});

it('throws exception for invalid id type', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: The [id] member must be a string, number.');
    $this->expectExceptionCode(-32600);

    JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1.5,
        'method' => 'initialize',
    ]);
});

it('throws exception for missing method', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: The [method] member is required and must be a string.');
    $this->expectExceptionCode(-32600);

    JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
    ]);
});

it('throws exception for non string method', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: The [method] member is required and must be a string.');
    $this->expectExceptionCode(-32600);

    JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 123,
    ]);
});
