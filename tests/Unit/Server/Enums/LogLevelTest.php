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
    expect(LogLevel::from('emergency'))->toBe(LogLevel::Emergency)
        ->and(LogLevel::from('alert'))->toBe(LogLevel::Alert)
        ->and(LogLevel::from('critical'))->toBe(LogLevel::Critical)
        ->and(LogLevel::from('error'))->toBe(LogLevel::Error)
        ->and(LogLevel::from('warning'))->toBe(LogLevel::Warning)
        ->and(LogLevel::from('notice'))->toBe(LogLevel::Notice)
        ->and(LogLevel::from('info'))->toBe(LogLevel::Info)
        ->and(LogLevel::from('debug'))->toBe(LogLevel::Debug);
});

it('handles case insensitive string conversion', function (): void {
    expect(LogLevel::from('EMERGENCY'))->toBe(LogLevel::Emergency)
        ->and(LogLevel::from('Error'))->toBe(LogLevel::Error)
        ->and(LogLevel::from('INFO'))->toBe(LogLevel::Info);
});

it('throws exception for invalid level string', function (): void {
    LogLevel::from('invalid');
})->throws(ValueError::class);
