<?php

use Laravel\Mcp\Server\Completions\ArrayCompletionResponse;
use Laravel\Mcp\Server\Completions\DirectCompletionResponse;

it('filters by prefix when resolved', function (): void {
    $result = new ArrayCompletionResponse(['php', 'python', 'javascript', 'go']);

    $resolved = $result->resolve('py');

    expect($resolved)->toBeInstanceOf(DirectCompletionResponse::class)
        ->and($resolved->values())->toBe(['python']);
});

it('starts with empty values until resolved', function (): void {
    $result = new ArrayCompletionResponse(['php', 'python', 'javascript']);

    expect($result->values())->toBe([])
        ->and($result->hasMore())->toBeFalse();
});
