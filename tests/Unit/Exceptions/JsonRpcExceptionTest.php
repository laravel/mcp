<?php

declare(strict_types=1);

use Laravel\Mcp\Exceptions\JsonRpcException;

it('converts to JsonRpc error without id and with data', function (): void {
    $exception = new JsonRpcException(
        message: 'Invalid params',
        code: -32602,
        requestId: null,
        data: ['hint' => 'Missing field']
    );

    $response = $exception->toJsonRpcResponse();

    expect($response->toArray())->toEqual([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32602,
            'message' => 'Invalid params',
            'data' => ['hint' => 'Missing field'],
        ],
    ]);
});

it('converts to JsonRpc error with id and without data', function (): void {
    $exception = new JsonRpcException(
        message: 'Not found',
        code: -32601,
        requestId: 'abc-123',
    );

    $response = $exception->toJsonRpcResponse();

    expect($response->toArray())->toEqual([
        'jsonrpc' => '2.0',
        'id' => 'abc-123',
        'error' => [
            'code' => -32601,
            'message' => 'Not found',
        ],
    ]);
});

it('coerces non-string and non-integer request ids to null', function (mixed $invalidId): void {
    $exception = new JsonRpcException(
        message: 'Invalid Request',
        code: -32600,
        requestId: $invalidId,
    );

    $response = $exception->toJsonRpcResponse();

    expect($response->toArray())->toEqual([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32600,
            'message' => 'Invalid Request',
        ],
    ]);
})->with([
    'float' => [1.0],
    'array' => [['foo' => 'bar']],
    'bool' => [true],
    'object' => [(object) ['foo' => 'bar']],
]);
