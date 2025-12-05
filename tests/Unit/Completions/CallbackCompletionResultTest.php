<?php

use Laravel\Mcp\Server\Completions\CallbackCompletionResult;
use Laravel\Mcp\Server\Completions\CompletionResult;
use Laravel\Mcp\Server\Completions\DirectCompletionResult;

it('executes callback when resolved', function (): void {
    $called = false;
    $result = new CallbackCompletionResult(function (string $value) use (&$called): array {
        $called = true;

        return ['result'];
    });

    $result->resolve('test');

    expect($called)->toBeTrue();
});

it('passes value to callback', function (): void {
    $receivedValue = null;
    $result = new CallbackCompletionResult(function (string $value) use (&$receivedValue): array {
        $receivedValue = $value;

        return ['result'];
    });

    $result->resolve('test-value');

    expect($receivedValue)->toBe('test-value');
});

it('handles CompletionResult return', function (): void {
    $result = new CallbackCompletionResult(
        fn (string $value): CompletionResult => CompletionResult::make(['custom', 'result'])
    );

    $resolved = $result->resolve('test');

    expect($resolved)->toBeInstanceOf(CompletionResult::class)
        ->and($resolved->values())->toBe(['custom', 'result']);
});

it('handles array return', function (): void {
    $result = new CallbackCompletionResult(fn (string $value): array => ['item1', 'item2', 'item3']);

    $resolved = $result->resolve('test');

    expect($resolved)->toBeInstanceOf(DirectCompletionResult::class)
        ->and($resolved->values())->toBe(['item1', 'item2', 'item3']);
});

it('handles string return', function (): void {
    $result = new CallbackCompletionResult(fn (string $value): string => 'single-item');

    $resolved = $result->resolve('test');

    expect($resolved->values())->toBe(['single-item']);
});

it('truncates callback results to 100 items', function (): void {
    $result = new CallbackCompletionResult(fn (string $value): array => array_map(fn ($i): string => "item{$i}", range(1, 150)));

    $resolved = $result->resolve('');

    expect($resolved->values())->toHaveCount(100);
});

it('starts with empty values until resolved', function (): void {
    $result = new CallbackCompletionResult(fn (string $value): array => ['result']);

    expect($result->values())->toBe([])
        ->and($result->total())->toBeNull()
        ->and($result->hasMore())->toBeFalse();
});
