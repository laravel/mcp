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

it('works with hasMore flag', function (): void {
    $result = new DirectCompletionResponse(['php', 'python'], hasMore: true);

    expect($result->values())->toBe(['php', 'python'])
        ->and($result->hasMore())->toBeTrue();
});

it('converts to array with hasMore true', function (): void {
    $result = new DirectCompletionResponse(['php', 'python'], hasMore: true);

    expect($result->toArray())->toBe([
        'values' => ['php', 'python'],
        'total' => 2,
        'hasMore' => true,
    ]);
});

it('throws exception when constructor receives more than 100 items', function (): void {
    $values = array_map(fn ($i): string => "item{$i}", range(1, 101));

    new DirectCompletionResponse($values);
})->throws(InvalidArgumentException::class, 'Completion values cannot exceed 100 items');

it('allows exactly 100 items', function (): void {
    $values = array_map(fn ($i): string => "item{$i}", range(1, 100));
    $result = new DirectCompletionResponse($values);

    expect($result->values())->toHaveCount(100);
});
