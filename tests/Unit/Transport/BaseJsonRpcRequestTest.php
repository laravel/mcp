<?php

use Laravel\Mcp\Transport\JsonRpcRequest;

it('creates a request with constructor', function (): void {
    $request = new JsonRpcRequest(1, 'tools/call', ['name' => 'echo']);

    expect($request->id)->toBe(1)
        ->and($request->method)->toBe('tools/call')
        ->and($request->params)->toBe(['name' => 'echo'])
        ->and($request->sessionId)->toBeNull();
});

it('serializes to array', function (): void {
    $request = new JsonRpcRequest(1, 'tools/call', ['name' => 'echo']);

    expect($request->toArray())->toBe([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'echo'],
    ]);
});

it('omits params when empty', function (): void {
    $request = new JsonRpcRequest(1, 'initialize', []);

    expect($request->toArray())->toBe([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
    ]);
});

it('serializes to json', function (): void {
    $request = new JsonRpcRequest(1, 'ping', []);

    $json = $request->toJson();

    expect(json_decode($json, true))->toBe([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'ping',
    ]);
});

it('delegates get cursor and meta to base class', function (): void {
    $request = new JsonRpcRequest(1, 'tools/list', [
        'cursor' => 'abc',
        '_meta' => ['token' => '123'],
    ]);

    expect($request->cursor())->toBe('abc')
        ->and($request->get('cursor'))->toBe('abc')
        ->and($request->meta())->toBe(['token' => '123']);
});

it('converts to mcp request', function (): void {
    $request = new JsonRpcRequest(1, 'tools/call', [
        'arguments' => ['name' => 'John'],
        '_meta' => ['requestId' => '456'],
    ], 'session-1');

    $mcpRequest = $request->toRequest();

    expect($mcpRequest->get('name'))->toBe('John')
        ->and($mcpRequest->sessionId())->toBe('session-1')
        ->and($mcpRequest->meta())->toBe(['requestId' => '456']);
});
