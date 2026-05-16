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
