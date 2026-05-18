<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\ProtocolVersion;

it('exposes a non-empty list of supported versions', function (): void {
    expect(ProtocolVersion::supported())->not->toBeEmpty();
});

it('lists the latest version first', function (): void {
    expect(ProtocolVersion::supported()[0])->toBe(ProtocolVersion::LATEST->value);
});

it('includes the latest version in supported list', function (): void {
    expect(ProtocolVersion::supported())->toContain(ProtocolVersion::LATEST->value);
});

it('returns only string values', function (): void {
    foreach (ProtocolVersion::supported() as $version) {
        expect($version)->toBeString();
    }
});

it('compares versions chronologically via atLeast', function (): void {
    expect(ProtocolVersion::V2025_11_25->atLeast(ProtocolVersion::V2025_06_18))->toBeTrue()
        ->and(ProtocolVersion::V2025_06_18->atLeast(ProtocolVersion::V2025_06_18))->toBeTrue()
        ->and(ProtocolVersion::V2025_03_26->atLeast(ProtocolVersion::V2025_06_18))->toBeFalse()
        ->and(ProtocolVersion::V2024_11_05->atLeast(ProtocolVersion::V2025_11_25))->toBeFalse();
});

it('gates instructions on 2025-06-18 and newer', function (ProtocolVersion $version, bool $supports): void {
    expect($version->supportsInstructions())->toBe($supports);
})->with([
    [ProtocolVersion::V2024_11_05, false],
    [ProtocolVersion::V2025_03_26, false],
    [ProtocolVersion::V2025_06_18, true],
    [ProtocolVersion::V2025_11_25, true],
]);

it('gates implementation metadata on 2025-11-25 and newer', function (ProtocolVersion $version, bool $supports): void {
    expect($version->supportsImplementationMetadata())->toBe($supports);
})->with([
    [ProtocolVersion::V2024_11_05, false],
    [ProtocolVersion::V2025_03_26, false],
    [ProtocolVersion::V2025_06_18, false],
    [ProtocolVersion::V2025_11_25, true],
]);
