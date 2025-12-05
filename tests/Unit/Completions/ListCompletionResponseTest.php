<?php

use Laravel\Mcp\Server\Completions\DirectCompletionResponse;
use Laravel\Mcp\Server\Completions\ListCompletionResponse;

it('filters by prefix when resolved', function (): void {
    $result = new ListCompletionResponse(['php', 'python', 'javascript', 'go']);

    $resolved = $result->resolve('py');

    expect($resolved)->toBeInstanceOf(DirectCompletionResponse::class)
        ->and($resolved->values())->toBe(['python']);
});

it('returns all items when empty value', function (): void {
    $result = new ListCompletionResponse(['php', 'python', 'javascript']);

    $resolved = $result->resolve('');

    expect($resolved->values())->toBe(['php', 'python', 'javascript']);
});

it('returns empty when no match', function (): void {
    $result = new ListCompletionResponse(['php', 'python', 'javascript']);

    $resolved = $result->resolve('rust');

    expect($resolved->values())->toBe([]);
});

it('is case insensitive', function (): void {
    $result = new ListCompletionResponse(['PHP', 'Python', 'JavaScript']);

    $resolved = $result->resolve('py');

    expect($resolved->values())->toBe(['Python']);
});

it('truncates to 100 items', function (): void {
    $items = array_map(fn ($i): string => "item{$i}", range(1, 150));
    $result = new ListCompletionResponse($items);

    $resolved = $result->resolve('');

    expect($resolved->values())->toHaveCount(100);
});

it('starts with empty values until resolved', function (): void {
    $result = new ListCompletionResponse(['php', 'python', 'javascript']);

    expect($result->values())->toBe([])
        ->and($result->total())->toBeNull()
        ->and($result->hasMore())->toBeFalse();
});
