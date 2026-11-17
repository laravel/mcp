<?php

declare(strict_types=1);

use Laravel\Mcp\Transport\HeaderValue;

it('leaves a header safe value untouched', function (): void {
    expect((string) new HeaderValue('us-west1'))->toBe('us-west1');
});

it('encodes a value that cannot be carried as plain ascii', function (string $value, string $encoded): void {
    expect((string) new HeaderValue($value))->toBe($encoded);
})->with([
    ['Hello, 世界', '=?base64?SGVsbG8sIOS4lueVjA==?='],
    [' padded ', '=?base64?IHBhZGRlZCA=?='],
    ["line1\nline2", '=?base64?bGluZTEKbGluZTI=?='],
    ['=?base64?literal?=', '=?base64?PT9iYXNlNjQ/bGl0ZXJhbD89?='],
]);

it('decodes a sentinel wrapped header back to its source value', function (): void {
    expect(HeaderValue::fromHeader('=?base64?SGVsbG8sIOS4lueVjA==?=')->value)->toBe('Hello, 世界');
});

it('leaves a header without the sentinel alone', function (): void {
    expect(HeaderValue::fromHeader('tools/call')->value)->toBe('tools/call');
});

it('keeps a sentinel wrapping undecodable content as it arrived', function (): void {
    expect(HeaderValue::fromHeader('=?base64?not base64!?=')->value)->toBe('=?base64?not base64!?=');
});

it('round trips every value it encodes', function (string $value): void {
    expect(HeaderValue::fromHeader((string) new HeaderValue($value))->value)->toBe($value);
})->with([
    'us-west1',
    'Hello, 世界',
    ' padded ',
    "line1\nline2",
    '=?base64?literal?=',
    'file:///projects/myapp/config.json',
]);

it('matches the body value it was decoded from', function (): void {
    expect(HeaderValue::fromHeader('=?base64?SGVsbG8sIOS4lueVjA==?=')->matches('Hello, 世界'))->toBeTrue()
        ->and(HeaderValue::fromHeader('get_weather')->matches('some-other-tool'))->toBeFalse();
});

it('keeps a sentinel wrapping unpadded base64 as it arrived', function (): void {
    expect(HeaderValue::fromHeader('=?base64?SGVsbG8?=')->value)->toBe('=?base64?SGVsbG8?=');
});

it('keeps a sentinel wrapping base64 with whitespace as it arrived', function (): void {
    expect(HeaderValue::fromHeader('=?base64?S G V s bG8=?=')->value)->toBe('=?base64?S G V s bG8=?=');
});

it('only treats visible ascii, space, and tab as safe', function (string $value, bool $safe): void {
    expect(HeaderValue::isSafe($value))->toBe($safe);
})->with([
    ['us-west1', true],
    ['us west'.chr(9).'1', true],
    ['Hello, 世界', false],
    ['a'.chr(10).'b', false],
    [chr(255), false],
]);
