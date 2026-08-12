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

    public function handshake(): ProtocolHandshake
    {
        return match ($this) {
            self::V2026_07_28 => ProtocolHandshake::Discovery,
            self::V2025_11_25, self::V2025_06_18, self::V2025_03_26, self::V2024_11_05 => ProtocolHandshake::Initialize,
        };
    }

    /** @return list<string> */
    public static function serverSupported(): array
    {
        return [self::LATEST->value];
    }

    /** @return list<string> */
    public static function clientSupported(): array
    {
        return [
            self::V2026_07_28->value,
            self::V2025_11_25->value,
            self::V2025_06_18->value,
        ];
    }

    /** @return list<string> */
    public static function initializeSupported(): array
    {
        return [
            self::V2025_11_25->value,
            self::V2025_06_18->value,
        ];
    }

    public static function preferredFrom(string ...$versions): ?self
    {
        foreach (self::cases() as $version) {
            if (in_array($version->value, self::clientSupported(), true) && in_array($version->value, $versions, true)) {
                return $version;
            }
        }

        return null;
    }
}
