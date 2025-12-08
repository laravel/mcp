<?php

use Laravel\Mcp\Server\Completions\DirectCompletionResponse;

it('returns self when resolved', function (): void {
    $result = new DirectCompletionResponse(['php', 'python']);

    $resolved = $result->resolve('py');

    expect($resolved)->toBe($result);
});

it('throws exception when the constructor receives more than 100 items', function (): void {
    $values = array_map(fn ($i): string => "item{$i}", range(1, 101));

    new DirectCompletionResponse($values);
})->throws(InvalidArgumentException::class, 'Completion values cannot exceed 100 items');

it('does not allow more than 100 items', function (): void {
    $values = array_map(fn ($i): string => "item{$i}", range(1, 100));
    $result = new DirectCompletionResponse($values);

    expect($result->values())->toHaveCount(100);
});
