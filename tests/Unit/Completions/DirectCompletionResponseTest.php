<?php

use Laravel\Mcp\Server\Completions\DirectCompletionResponse;

it('returns self when resolved', function (): void {
    $result = new DirectCompletionResponse(['php', 'python']);

    $resolved = $result->resolve('py');

    expect($resolved)->toBe($result);
});

it('contains provided values', function (): void {
    $result = new DirectCompletionResponse(['php', 'python', 'javascript']);

    expect($result->values())->toBe(['php', 'python', 'javascript']);
});

it('works with metadata', function (): void {
    $result = new DirectCompletionResponse(['php', 'python'], total: 10, hasMore: true);

    expect($result->values())->toBe(['php', 'python'])
        ->and($result->total())->toBe(10)
        ->and($result->hasMore())->toBeTrue();
});

it('converts to array correctly', function (): void {
    $result = new DirectCompletionResponse(['php', 'python'], total: 5, hasMore: true);

    expect($result->toArray())->toBe([
        'values' => ['php', 'python'],
        'total' => 5,
        'hasMore' => true,
    ]);
});

it('converts to array without total when null', function (): void {
    $result = new DirectCompletionResponse(['php', 'python']);

    expect($result->toArray())->toBe([
        'values' => ['php', 'python'],
        'hasMore' => false,
    ]);
});
