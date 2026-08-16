<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\ProtocolHandshake;
use Laravel\Mcp\Enums\ProtocolVersion;

it('defines the handshake for each protocol version explicitly', function (): void {
    expect(ProtocolVersion::V2026_07_28->handshake())->toBe(ProtocolHandshake::Discovery)
        ->and(ProtocolVersion::V2025_11_25->handshake())->toBe(ProtocolHandshake::Initialize)
        ->and(ProtocolVersion::V2025_06_18->handshake())->toBe(ProtocolHandshake::Initialize);
});

it('lists versions supported by each protocol flow', function (): void {
    expect(ProtocolVersion::serverSupported())->toBe(['2026-07-28'])
        ->and(ProtocolVersion::clientSupported())->toBe(['2026-07-28', '2025-11-25', '2025-06-18'])
        ->and(ProtocolVersion::initializeSupported())->toBe(['2025-11-25', '2025-06-18']);
});

it('picks the version this client prefers regardless of the order the server lists', function (): void {
    expect(ProtocolVersion::preferredFrom('2025-06-18', '2025-11-25'))->toBe(ProtocolVersion::V2025_11_25);
    expect(ProtocolVersion::preferredFrom('2025-06-18', '2026-07-28'))->toBe(ProtocolVersion::LATEST);
});

it('has no mutual version when the server shares none', function (): void {
    expect(ProtocolVersion::preferredFrom('2024-11-05', '2027-01-01'))->toBeNull();
});

it('declares the cases newest first, which is the preference order', function (): void {
    $values = array_column(ProtocolVersion::cases(), 'value');
    $sorted = $values;

    rsort($sorted);

    expect($values)->toBe($sorted)
        ->and(ProtocolVersion::cases()[0])->toBe(ProtocolVersion::LATEST);
});
