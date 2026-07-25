<?php

declare(strict_types=1);

namespace Laravel\Mcp\Enums;

enum ProtocolVersion: string
{
    case V2026_07_28 = '2026-07-28';
    case V2025_11_25 = '2025-11-25';
    case V2025_06_18 = '2025-06-18';
    case V2025_03_26 = '2025-03-26';
    case V2024_11_05 = '2024-11-05';

    public const LATEST = self::V2026_07_28;

    public const LATEST_LEGACY = self::V2025_11_25;

    /**
     * @return array<int, string>
     */
    public static function supported(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, string>
     */
    public static function clientSupported(): array
    {
        return [
            self::V2026_07_28->value,
            self::V2025_11_25->value,
            self::V2025_06_18->value,
        ];
    }

    /**
     * Determine if the given revision conveys protocol metadata per-request
     * instead of negotiating a session with an initialize handshake.
     */
    public static function isModern(string $version): bool
    {
        return $version >= self::V2026_07_28->value;
    }

    /**
     * The newest handshake-based revision within the given supported list.
     *
     * @param  array<int, string>  $supportedVersions
     */
    public static function latestLegacy(array $supportedVersions): ?string
    {
        $legacy = array_filter($supportedVersions, fn (string $version): bool => ! self::isModern($version));

        return $legacy === [] ? null : max($legacy);
    }
}
