<?php

use Laravel\Mcp\Server\Completions\CompletionResponse;

it('creates a completion result with values', function (): void {
    $result = CompletionResponse::from(['php', 'python', 'javascript']);

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
    $result = CompletionResponse::from($values);

    expect($result->values())->toHaveCount(100)
        ->and($result->hasMore())->toBeTrue();
});

it('supports single string in from', function (): void {
    $result = CompletionResponse::from('single-value');

    expect($result->values())->toBe(['single-value']);
});
