<?php

use Laravel\Mcp\Transport\JsonRpcResponse;

it('parses json to array using fromJson', function (): void {
    $json = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['tools' => []],
    ]);

    $result = JsonRpcResponse::fromJson($json);

    expect($result)->toBe([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['tools' => []],
    ]);
});

it('returns empty array for invalid json', function (): void {
    $result = JsonRpcResponse::fromJson('not-json');

    expect($result)->toBe([]);
});

it('creates result response', function (): void {
    $response = JsonRpcResponse::result(1, ['foo' => 'bar']);

    expect($response->toArray())->toBe([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['foo' => 'bar'],
    ]);
});

it('creates error response', function (): void {
    $response = JsonRpcResponse::error(1, -32600, 'Invalid Request');

    expect($response->toArray())->toBe([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => [
            'code' => -32600,
            'message' => 'Invalid Request',
        ],
    ]);
});

it('creates notification response', function (): void {
    $response = JsonRpcResponse::notification('notifications/progress', ['progress' => 50]);

    expect($response->toArray())->toBe([
        'jsonrpc' => '2.0',
        'method' => 'notifications/progress',
        'params' => ['progress' => 50],
    ]);
});
