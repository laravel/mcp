<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\LogLevel;

test('it has correct severity values', function (): void {
    expect(LogLevel::Emergency->severity())->toBe(0);
    expect(LogLevel::Alert->severity())->toBe(1);
    expect(LogLevel::Critical->severity())->toBe(2);
    expect(LogLevel::Error->severity())->toBe(3);
    expect(LogLevel::Warning->severity())->toBe(4);
    expect(LogLevel::Notice->severity())->toBe(5);
    expect(LogLevel::Info->severity())->toBe(6);
    expect(LogLevel::Debug->severity())->toBe(7);
});

test('it correctly determines if a log should be sent', function (): void {
    expect(LogLevel::Emergency->shouldLog(LogLevel::Info))->toBeTrue();
    expect(LogLevel::Error->shouldLog(LogLevel::Info))->toBeTrue();
    expect(LogLevel::Info->shouldLog(LogLevel::Info))->toBeTrue();
    expect(LogLevel::Debug->shouldLog(LogLevel::Info))->toBeFalse();

    expect(LogLevel::Error->shouldLog(LogLevel::Error))->toBeTrue();
    expect(LogLevel::Warning->shouldLog(LogLevel::Error))->toBeFalse();
    expect(LogLevel::Info->shouldLog(LogLevel::Error))->toBeFalse();

    expect(LogLevel::Emergency->shouldLog(LogLevel::Debug))->toBeTrue();
    expect(LogLevel::Debug->shouldLog(LogLevel::Debug))->toBeTrue();
    expect(LogLevel::Info->shouldLog(LogLevel::Debug))->toBeTrue();
});

test('it can be created from string', function (): void {
    expect(LogLevel::fromString('emergency'))->toBe(LogLevel::Emergency);
    expect(LogLevel::fromString('alert'))->toBe(LogLevel::Alert);
    expect(LogLevel::fromString('critical'))->toBe(LogLevel::Critical);
    expect(LogLevel::fromString('error'))->toBe(LogLevel::Error);
    expect(LogLevel::fromString('warning'))->toBe(LogLevel::Warning);
    expect(LogLevel::fromString('notice'))->toBe(LogLevel::Notice);
    expect(LogLevel::fromString('info'))->toBe(LogLevel::Info);
    expect(LogLevel::fromString('debug'))->toBe(LogLevel::Debug);
});

test('it handles case insensitive string conversion', function (): void {
    expect(LogLevel::fromString('EMERGENCY'))->toBe(LogLevel::Emergency);
    expect(LogLevel::fromString('Error'))->toBe(LogLevel::Error);
    expect(LogLevel::fromString('INFO'))->toBe(LogLevel::Info);
});

test('it throws exception for invalid level string', function (): void {
    LogLevel::fromString('invalid');
})->throws(ValueError::class);
