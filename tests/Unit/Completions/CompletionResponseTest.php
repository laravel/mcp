<?php

use Laravel\Mcp\Server\Completions\CompletionResponse;

it('creates a completion result with values', function (): void {
    $result = CompletionResponse::match(['php', 'python', 'javascript'])->resolve('');

    expect($result->values())->toBe(['php', 'python', 'javascript'])
        ->and($result->hasMore())->toBeFalse();
});

it('creates an empty completion result', function (): void {
    $result = CompletionResponse::empty();

    expect($result->values())->toBe([])
        ->and($result->hasMore())->toBeFalse();
});

it('auto-truncates values to 100 items and sets hasMore', function (): void {
    $values = array_map(fn ($i): string => "item{$i}", range(1, 150));
    $result = CompletionResponse::match($values)->resolve('');

    expect($result->values())->toHaveCount(100)
        ->and($result->hasMore())->toBeTrue();
});

it('returns raw array data without filtering using a result', function (): void {
    $result = CompletionResponse::result(['apple', 'apricot', 'banana'])->resolve('ap');

    expect($result->values())->toBe(['apple', 'apricot', 'banana'])
        ->and($result->hasMore())->toBeFalse();
});

it('applies filtering with match but not with result', function (): void {
    $items = ['apple', 'apricot', 'banana'];

    $matchResult = CompletionResponse::match($items)->resolve('ap');
    $resultResult = CompletionResponse::result($items)->resolve('ap');

    expect($matchResult->values())->toBe(['apple', 'apricot'])
        ->and($resultResult->values())->toBe(['apple', 'apricot', 'banana']);
});

it('returns single string as array', function (): void {
    $result = CompletionResponse::result('single-value')->resolve('');

    expect($result->values())->toBe(['single-value'])
        ->and($result->hasMore())->toBeFalse();
});

it('truncates raw array data to 100 items', function (): void {
    $values = array_map(fn ($i): string => "item{$i}", range(1, 150));
    $result = CompletionResponse::result($values)->resolve('');

    expect($result->values())->toHaveCount(100)
        ->and($result->hasMore())->toBeTrue();
});

it('handles callbacks with result method', function (): void {
    $result = CompletionResponse::result(
        fn (string $value): array => array_filter(
            ['apple', 'apricot', 'banana'],
            fn (string $item): bool => str_starts_with($item, $value)
        )
    )->resolve('ap');

    expect($result->values())->toBe(['apple', 'apricot']);
});
