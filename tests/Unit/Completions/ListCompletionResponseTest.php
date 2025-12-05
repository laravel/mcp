<?php

use Laravel\Mcp\Server\Completions\ArrayCompletionResponse;
use Laravel\Mcp\Server\Completions\DirectCompletionResponse;

it('filters by prefix when resolved', function (): void {
    $result = new ArrayCompletionResponse(['php', 'python', 'javascript', 'go']);

    $resolved = $result->resolve('py');

    expect($resolved)->toBeInstanceOf(DirectCompletionResponse::class)
        ->and($resolved->values())->toBe(['python']);
});

it('returns all items when empty value', function (): void {
    $result = new ArrayCompletionResponse(['php', 'python', 'javascript']);

    $resolved = $result->resolve('');

    expect($resolved->values())->toBe(['php', 'python', 'javascript']);
});

it('returns empty when no match', function (): void {
    $result = new ArrayCompletionResponse(['php', 'python', 'javascript']);

    $resolved = $result->resolve('rust');

    expect($resolved->values())->toBe([]);
});

it('is case insensitive', function (): void {
    $result = new ArrayCompletionResponse(['PHP', 'Python', 'JavaScript']);

    $resolved = $result->resolve('py');

    expect($resolved->values())->toBe(['Python']);
});

it('truncates to 100 items and sets hasMore', function (): void {
    $items = array_map(fn ($i): string => "item{$i}", range(1, 150));
    $result = new ArrayCompletionResponse($items);

    $resolved = $result->resolve('');

    expect($resolved->values())->toHaveCount(100)
        ->and($resolved->hasMore())->toBeTrue();
});

it('starts with empty values until resolved', function (): void {
    $result = new ArrayCompletionResponse(['php', 'python', 'javascript']);

    expect($result->values())->toBe([])
        ->and($result->hasMore())->toBeFalse();
});
