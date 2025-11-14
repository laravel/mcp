<?php

use Laravel\Mcp\Server\Transport\JsonRpcResponse;

it('can return response as array', function (): void {
    $response = JsonRpcResponse::result(1, ['foo' => 'bar']);

    $expectedArray = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['foo' => 'bar'],
    ];

    expect($response->toArray())->toEqual($expectedArray);
});

it('can return response as json', function (): void {
    $response = JsonRpcResponse::result(1, ['foo' => 'bar']);

    $expectedJson = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['foo' => 'bar'],
    ]);

    expect($response->toJson())->toEqual($expectedJson);
});

it('converts empty array result to object', function (): void {
    $response = JsonRpcResponse::result(1, []);

    $expectedJson = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => (object) [],
    ]);

    expect($response->toJson())->toEqual($expectedJson);
});

it('includes _meta in result when provided in result array', function (): void {
    $response = JsonRpcResponse::result(
        1,
        [
            'content' => 'Hello',
            '_meta' => [
                'requestId' => '123',
                'timestamp' => 1234567890,
            ],
        ]
    );

    $expectedArray = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'content' => 'Hello',
            '_meta' => [
                'requestId' => '123',
                'timestamp' => 1234567890,
            ],
        ],
    ];

    expect($response->toArray())->toEqual($expectedArray);
});

it('does not include _meta when not in result', function (): void {
    $response = JsonRpcResponse::result(1, ['content' => 'Hello']);

    expect($response->toArray()['result'])->not->toHaveKey('_meta');
});
