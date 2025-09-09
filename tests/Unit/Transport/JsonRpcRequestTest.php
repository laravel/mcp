<?php

use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;

it('can create a message from valid json', function (): void {
    $json = '{"jsonrpc": "2.0", "id": 1, "method": "tools/call", "params": {"name": "echo", "arguments": {"message": "Hello, world!"}}}';
    $request = JsonRpcRequest::fromJson($json);

    expect($request)->toBeInstanceOf(JsonRpcRequest::class)
        ->and($request->id)->toEqual(1)
        ->and($request->method)->toEqual('tools/call')
        ->and($request->params)->toEqual(['name' => 'echo', 'arguments' => ['message' => 'Hello, world!']]);
});

it('can create a notification message from valid json', function (): void {
    $json = '{"jsonrpc": "2.0", "method": "notifications/initialized"}';
    $request = JsonRpcRequest::fromJson($json);

    expect($request)->toBeInstanceOf(JsonRpcRequest::class)
        ->and($request->id)->toBeNull()
        ->and($request->method)->toEqual('notifications/initialized')
        ->and($request->params)->toEqual([]);
});

it('throws exception for invalid json', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Parse error');
    $this->expectExceptionCode(-32700);

    JsonRpcRequest::fromJson('invalid_json');
});

it('throws exception for missing jsonrpc version', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: Invalid JSON-RPC version. Must be "2.0".');
    $this->expectExceptionCode(-32600);

    JsonRpcRequest::fromJson('{"id": 1, "method": "initialize"}');
});

it('throws exception for incorrect jsonrpc version', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: Invalid JSON-RPC version. Must be "2.0".');
    $this->expectExceptionCode(-32600);

    JsonRpcRequest::fromJson('{"jsonrpc": "1.0", "id": 1, "method": "initialize"}');
});

it('throws exception for invalid id type', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid params: "id" must be an integer or null if present.');
    $this->expectExceptionCode(-32602);

    JsonRpcRequest::fromJson('{"jsonrpc": "2.0", "id": "not-an-integer", "method": "initialize"}');
});

it('throws exception for missing method', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: Invalid or missing "method". Must be a string.');
    $this->expectExceptionCode(-32600);

    JsonRpcRequest::fromJson('{"jsonrpc": "2.0", "id": 1}');
});

it('throws exception for non string method', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: Invalid or missing "method". Must be a string.');
    $this->expectExceptionCode(-32600);

    JsonRpcRequest::fromJson('{"jsonrpc": "2.0", "id": 1, "method": 123}');
});
