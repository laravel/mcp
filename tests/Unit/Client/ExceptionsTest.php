<?php

use Laravel\Mcp\Client\Exceptions\ClientException;
use Laravel\Mcp\Client\Exceptions\ConnectionException;

it('client exception extends runtime exception', function (): void {
    $exception = new ClientException('test error', 42);

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toBe('test error')
        ->and($exception->getCode())->toBe(42);
});

it('connection exception extends client exception', function (): void {
    $exception = new ConnectionException('connection failed');

    expect($exception)->toBeInstanceOf(ClientException::class)
        ->and($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toBe('connection failed');
});
