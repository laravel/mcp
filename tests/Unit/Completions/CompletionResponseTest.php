<?php

use Laravel\Mcp\Server\Completions\CompletionResponse;
use Laravel\Mcp\Server\Completions\DirectCompletionResponse;

it('creates completion result with values', function (): void {
    $result = CompletionResponse::make(['php', 'python', 'javascript']);

    expect($result->values())->toBe(['php', 'python', 'javascript'])
        ->and($result->hasMore())->toBeFalse()
        ->and($result->total())->toBeNull();
});

it('creates empty completion result', function (): void {
    $result = CompletionResponse::empty();

    expect($result->values())->toBe([])
        ->and($result->hasMore())->toBeFalse()
        ->and($result->total())->toBeNull();
});

it('converts to array format', function (): void {
    $result = CompletionResponse::make(['php', 'python']);

    expect($result->toArray())->toBe([
        'values' => ['php', 'python'],
        'hasMore' => false,
    ]);
});

it('includes total in array when provided', function (): void {
    $result = new DirectCompletionResponse(['php', 'python'], total: 5, hasMore: true);

    expect($result->toArray())->toBe([
        'values' => ['php', 'python'],
        'total' => 5,
        'hasMore' => true,
    ]);
});

it('auto-truncates values to 100 items', function (): void {
    $values = array_map(fn ($i): string => "item{$i}", range(1, 150));
    $result = CompletionResponse::make($values);

    expect($result->values())->toHaveCount(100);
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

it('supports single string in make', function (): void {
    $result = CompletionResponse::make('single-value');

    expect($result->values())->toBe(['single-value']);
});

it('creates usingList result', function (): void {
    $result = CompletionResponse::usingList(['php', 'python', 'javascript']);
    $resolved = $result->resolve('py');

    expect($resolved->values())->toBe(['python']);
});

it('creates usingEnum result with backed enum', function (): void {
    enum TestBackedEnum: string
    {
        case One = 'value-one';
        case Two = 'value-two';
    }

    $result = CompletionResponse::usingEnum(TestBackedEnum::class);
    $resolved = $result->resolve('value-o');

    expect($resolved->values())->toBe(['value-one']);
});

it('creates usingEnum result with non-backed enum', function (): void {
    enum TestEnum
    {
        case Active;
        case Inactive;
    }

    $result = CompletionResponse::usingEnum(TestEnum::class);
    $resolved = $result->resolve('act');

    expect($resolved->values())->toBe(['Active']);
});

it('throws exception for invalid enum class', function (): void {
    CompletionResponse::usingEnum('NotAnEnum');
})->throws(InvalidArgumentException::class, 'is not an enum');

it('creates using callback result', function (): void {
    $result = CompletionResponse::using(fn (string $value): \Laravel\Mcp\Server\Completions\CompletionResponse => CompletionResponse::make(['test-value']));
    $resolved = $result->resolve('test');

    expect($resolved->values())->toBe(['test-value']);
});

it('callback can return array', function (): void {
    $result = CompletionResponse::using(fn (string $value): array => ['value1', 'value2']);
    $resolved = $result->resolve('val');

    expect($resolved->values())->toBe(['value1', 'value2']);
});

it('resolve returns self for direct type', function (): void {
    $result = CompletionResponse::make(['php', 'python']);
    $resolved = $result->resolve('py');

    expect($resolved)->toBe($result);
});
