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

it('supports only the versions that define the protocol version header as a client', function (): void {
    expect(ProtocolVersion::clientSupported())->toBe([
        ProtocolVersion::V2026_07_28->value,
        ProtocolVersion::V2025_11_25->value,
        ProtocolVersion::V2025_06_18->value,
    ]);
});

it('identifies modern revisions', function (): void {
    expect(ProtocolVersion::isModern('2026-07-28'))->toBeTrue();
    expect(ProtocolVersion::isModern('2027-01-01'))->toBeTrue();
    expect(ProtocolVersion::isModern('2025-11-25'))->toBeFalse();
    expect(ProtocolVersion::isModern('2024-11-05'))->toBeFalse();
});

it('resolves the latest legacy version from a supported list', function (): void {
    expect(ProtocolVersion::latestLegacy(ProtocolVersion::supported()))->toBe('2025-11-25');
    expect(ProtocolVersion::latestLegacy(['2026-07-28']))->toBeNull();
    expect(ProtocolVersion::latestLegacy(['2025-03-26', '2025-06-18']))->toBe('2025-06-18');
});
