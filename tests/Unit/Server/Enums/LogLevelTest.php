<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\LogLevel;

it('has correct severity values', function (): void {
    expect(LogLevel::Emergency->severity())->toBe(0)
        ->and(LogLevel::Alert->severity())->toBe(1)
        ->and(LogLevel::Critical->severity())->toBe(2)
        ->and(LogLevel::Error->severity())->toBe(3)
        ->and(LogLevel::Warning->severity())->toBe(4)
        ->and(LogLevel::Notice->severity())->toBe(5)
        ->and(LogLevel::Info->severity())->toBe(6)
        ->and(LogLevel::Debug->severity())->toBe(7);
});

it('correctly determines if a log should be sent', function (): void {
    expect(LogLevel::Emergency->shouldLog(LogLevel::Info))->toBeTrue()
        ->and(LogLevel::Error->shouldLog(LogLevel::Info))->toBeTrue()
        ->and(LogLevel::Info->shouldLog(LogLevel::Info))->toBeTrue()
        ->and(LogLevel::Debug->shouldLog(LogLevel::Info))->toBeFalse()
        ->and(LogLevel::Error->shouldLog(LogLevel::Error))->toBeTrue()
        ->and(LogLevel::Warning->shouldLog(LogLevel::Error))->toBeFalse()
        ->and(LogLevel::Info->shouldLog(LogLevel::Error))->toBeFalse()
        ->and(LogLevel::Emergency->shouldLog(LogLevel::Debug))->toBeTrue()
        ->and(LogLevel::Debug->shouldLog(LogLevel::Debug))->toBeTrue()
        ->and(LogLevel::Info->shouldLog(LogLevel::Debug))->toBeTrue();

});

it('can be created from string', function (): void {
    expect(LogLevel::fromString('emergency'))->toBe(LogLevel::Emergency)
        ->and(LogLevel::fromString('alert'))->toBe(LogLevel::Alert)
        ->and(LogLevel::fromString('critical'))->toBe(LogLevel::Critical)
        ->and(LogLevel::fromString('error'))->toBe(LogLevel::Error)
        ->and(LogLevel::fromString('warning'))->toBe(LogLevel::Warning)
        ->and(LogLevel::fromString('notice'))->toBe(LogLevel::Notice)
        ->and(LogLevel::fromString('info'))->toBe(LogLevel::Info)
        ->and(LogLevel::fromString('debug'))->toBe(LogLevel::Debug);
});

it('handles case insensitive string conversion', function (): void {
    expect(LogLevel::fromString('EMERGENCY'))->toBe(LogLevel::Emergency)
        ->and(LogLevel::fromString('Error'))->toBe(LogLevel::Error)
        ->and(LogLevel::fromString('INFO'))->toBe(LogLevel::Info);
});

it('throws exception for invalid level string', function (): void {
    LogLevel::fromString('invalid');
})->throws(ValueError::class);
