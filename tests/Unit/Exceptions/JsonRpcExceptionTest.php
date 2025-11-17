<?php

use Laravel\Mcp\Server\Exceptions\JsonRpcException;

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
