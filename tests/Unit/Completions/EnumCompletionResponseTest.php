<?php

use Laravel\Mcp\Server\Completions\DirectCompletionResponse;
use Laravel\Mcp\Server\Completions\EnumCompletionResponse;

enum BackedEnumForTest: string
{
    case One = 'value-one';
    case Two = 'value-two';
    case Three = 'value-three';
}

enum PlainEnumForTest
{
    case Active;
    case Inactive;
    case Pending;
}

it('extracts backed enum values', function (): void {
    $result = new EnumCompletionResponse(BackedEnumForTest::class);

    $resolved = $result->resolve('');

    expect($resolved)->toBeInstanceOf(DirectCompletionResponse::class)
        ->and($resolved->values())->toBe(['value-one', 'value-two', 'value-three']);
});

it('extracts non-backed enum names', function (): void {
    $result = new EnumCompletionResponse(PlainEnumForTest::class);

    $resolved = $result->resolve('');

    expect($resolved->values())->toBe(['Active', 'Inactive', 'Pending']);
});

it('filters enum values by prefix', function (): void {
    $result = new EnumCompletionResponse(BackedEnumForTest::class);

    $resolved = $result->resolve('value-t');

    expect($resolved->values())->toBe(['value-two', 'value-three']);
});

it('throws exception for invalid enum class', function (): void {
    new EnumCompletionResponse('NotAnEnum');
})->throws(InvalidArgumentException::class, 'is not an enum');

it('is case insensitive', function (): void {
    $result = new EnumCompletionResponse(PlainEnumForTest::class);

    $resolved = $result->resolve('act');

    expect($resolved->values())->toBe(['Active']);
});

it('starts with empty values until resolved', function (): void {
    $result = new EnumCompletionResponse(BackedEnumForTest::class);

    expect($result->values())->toBe([])
        ->and($result->total())->toBeNull()
        ->and($result->hasMore())->toBeFalse();
});
