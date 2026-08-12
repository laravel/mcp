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

it('serves only the latest revision', function (): void {
    expect(ProtocolVersion::supported())->toBe(['2026-07-28']);
});

it('returns only string values', function (): void {
    foreach (ProtocolVersion::supported() as $version) {
        expect($version)->toBeString();
    }
});

it('supports only the versions that define the protocol version header as a client', function (): void {
    expect(ProtocolVersion::clientSupported())->toBe([
        ProtocolVersion::V2025_11_25->value,
        ProtocolVersion::V2025_06_18->value,
    ]);
});

it('picks the version this client prefers regardless of the order the server lists', function (): void {
    expect(ProtocolVersion::mutual(['2025-06-18', '2025-11-25']))->toBe(ProtocolVersion::V2025_11_25);
    expect(ProtocolVersion::mutual(['2025-06-18', '2026-07-28']))->toBe(ProtocolVersion::LATEST);
});

it('has no mutual version when the server shares none', function (): void {
    expect(ProtocolVersion::mutual(['2024-11-05', '2027-01-01']))->toBeNull();
});
