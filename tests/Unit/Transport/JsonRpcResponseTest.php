<?php

use Laravel\Mcp\Server\Transport\JsonRpcResult;

it('can return response as array', function () {
    $response = JsonRpcResult::create(1, ['foo' => 'bar']);

    $expectedArray = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['foo' => 'bar'],
    ];

    expect($response->toArray())->toEqual($expectedArray);
});

it('can return response as json', function () {
    $response = JsonRpcResult::create(1, ['foo' => 'bar']);

    $expectedJson = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['foo' => 'bar'],
    ]);

    expect($response->toJson())->toEqual($expectedJson);
});

it('converts empty array result to object', function () {
    $response = JsonRpcResult::create(1, []);

    $expectedJson = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => (object) [],
    ]);

    expect($response->toJson())->toEqual($expectedJson);
});
