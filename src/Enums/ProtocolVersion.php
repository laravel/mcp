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

    /**
     * @return array<int, string>
     */
    public static function supported(): array
    {
        return [self::LATEST->value];
    }

    /**
     * @return array<int, string>
     */
    public static function clientSupported(): array
    {
        return [
            self::V2025_11_25->value,
            self::V2025_06_18->value,
        ];
    }
}
