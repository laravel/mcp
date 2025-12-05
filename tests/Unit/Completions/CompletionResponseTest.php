<?php

use Laravel\Mcp\Server\Completions\ArrayCompletionResponse;
use Laravel\Mcp\Server\Completions\CallbackCompletionResponse;
use Laravel\Mcp\Server\Completions\CompletionResponse;
use Laravel\Mcp\Server\Completions\EnumCompletionResponse;

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

it('converts to array format', function (): void {
    $result = CompletionResponse::from(['php', 'python']);

    expect($result->toArray())->toBe([
        'values' => ['php', 'python'],
        'total' => 2,
        'hasMore' => false,
    ]);
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

it('fromArray creates ArrayCompletionResponse', function (): void {
    $result = CompletionResponse::fromArray(['php', 'python', 'javascript']);

    expect($result)->toBeInstanceOf(ArrayCompletionResponse::class);
});

it('fromEnum creates EnumCompletionResponse', function (): void {
    enum FactoryTestEnum: string
    {
        case One = 'value-one';
    }

    $result = CompletionResponse::fromEnum(FactoryTestEnum::class);

    expect($result)->toBeInstanceOf(EnumCompletionResponse::class);
});

it('fromCallback creates CallbackCompletionResponse', function (): void {
    $result = CompletionResponse::fromCallback(fn (string $value): array => ['test']);

    expect($result)->toBeInstanceOf(CallbackCompletionResponse::class);
});

it('resolve returns self for direct type', function (): void {
    $result = CompletionResponse::from(['php', 'python']);
    $resolved = $result->resolve('py');

    expect($resolved)->toBe($result);
});
