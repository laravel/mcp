<?php

use Laravel\Mcp\Server\Completions\CallbackCompletionResponse;
use Laravel\Mcp\Server\Completions\CompletionResponse;
use Laravel\Mcp\Server\Completions\DirectCompletionResponse;

it('executes a callback with the provided value when resolved', function (): void {
    $receivedValue = null;
    $result = new CallbackCompletionResponse(function (string $value) use (&$receivedValue): array {
        $receivedValue = $value;

        return ['result'];
    });

    $result->resolve('test-value');

    expect($receivedValue)->toBe('test-value');
});

it('handles CompletionResult return', function (): void {
    $result = new CallbackCompletionResponse(
        fn (string $value): CompletionResponse => new DirectCompletionResponse(['custom', 'result'])
    );

    $resolved = $result->resolve('test');

    expect($resolved)->toBeInstanceOf(CompletionResponse::class)
        ->and($resolved->values())->toBe(['custom', 'result']);
});

it('handles array return', function (): void {
    $result = new CallbackCompletionResponse(fn (string $value): array => ['item1', 'item2', 'item3']);

    $resolved = $result->resolve('test');

    expect($resolved)->toBeInstanceOf(DirectCompletionResponse::class)
        ->and($resolved->values())->toBe(['item1', 'item2', 'item3']);
});

it('handles string return', function (): void {
    $result = new CallbackCompletionResponse(fn (string $value): string => 'single-item');

    $resolved = $result->resolve('test');

    expect($resolved->values())->toBe(['single-item']);
});
