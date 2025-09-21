<?php

use Laravel\Mcp\Server\Exceptions\JsonRpcException;
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

it('stores session id when provided', function (): void {
    $sessionId = 'i-am-your-session-luke';
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
    ], $sessionId);

    expect($request->sessionId)->toBe($sessionId);
});

it('throws exception for missing jsonrpc version', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: The [jsonrpc] member must be exactly [2.0].');
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

it('defaults params to empty array and supports getters', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 'abc',
        'method' => 'something/do',
        // no params
    ]);

    expect($request->params)->toEqual([])
        ->and($request->get('missing', 'default'))->toEqual('default')
        ->and($request->cursor())->toBeNull();

    $requestWithCursor = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'other',
        'params' => ['cursor' => 'CUR123', 'foo' => 'bar'],
    ]);

    expect($requestWithCursor->cursor())->toEqual('CUR123')
        ->and($requestWithCursor->get('foo'))->toEqual('bar');
});
