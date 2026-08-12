<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\ProtocolHandshake;
use Laravel\Mcp\Enums\ProtocolVersion;

it('defines the handshake for each protocol version explicitly', function (): void {
    expect(ProtocolVersion::V2026_07_28->handshake())->toBe(ProtocolHandshake::Discovery)
        ->and(ProtocolVersion::V2025_11_25->handshake())->toBe(ProtocolHandshake::Initialize)
        ->and(ProtocolVersion::V2025_06_18->handshake())->toBe(ProtocolHandshake::Initialize);
});

it('identifies versions supported by the client', function (): void {
    expect(ProtocolVersion::V2026_07_28->isSupportedByClient())->toBeTrue()
        ->and(ProtocolVersion::V2025_11_25->isSupportedByClient())->toBeTrue()
        ->and(ProtocolVersion::V2025_06_18->isSupportedByClient())->toBeTrue()
        ->and(ProtocolVersion::V2025_03_26->isSupportedByClient())->toBeFalse();
});

it('picks the version this client prefers regardless of the order the server lists', function (): void {
    expect(ProtocolVersion::preferredFrom('2025-06-18', '2025-11-25'))->toBe(ProtocolVersion::V2025_11_25);
    expect(ProtocolVersion::preferredFrom('2025-06-18', '2026-07-28'))->toBe(ProtocolVersion::LATEST);
});

it('has no mutual version when the server shares none', function (): void {
    expect(ProtocolVersion::preferredFrom('2024-11-05', '2027-01-01'))->toBeNull();
});
