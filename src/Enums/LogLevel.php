<?php

declare(strict_types=1);

namespace Laravel\Mcp\Enums;

enum LogLevel: string
{
    case Emergency = 'emergency';
    case Alert = 'alert';
    case Critical = 'critical';
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Info = 'info';
    case Debug = 'debug';

    public function severity(): int
    {
        return match ($this) {
            self::Emergency => 0,
            self::Alert => 1,
            self::Critical => 2,
            self::Error => 3,
            self::Warning => 4,
            self::Notice => 5,
            self::Info => 6,
            self::Debug => 7,
        };
    }

    public function shouldLog(LogLevel $configuredLevel): bool
    {
        return $this->severity() <= $configuredLevel->severity();
    }

    public static function fromString(string $level): self
    {
        return self::from(strtolower($level));
    }
}
