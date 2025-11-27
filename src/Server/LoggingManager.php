<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Laravel\Mcp\Server\Enums\LogLevel;
use Laravel\Mcp\Server\Store\SessionStoreManager;

class LoggingManager
{
    protected const LOG_LEVEL_KEY = 'log_level';

    protected static LogLevel $defaultLevel = LogLevel::Info;

    public function __construct(
        protected SessionStoreManager $session,
    ) {
        //
    }

    public function setLevel(LogLevel $level): void
    {
        $this->session->set(self::LOG_LEVEL_KEY, $level);
    }

    public function getLevel(): LogLevel
    {
        if ($this->session->sessionId() === null) {
            return self::$defaultLevel;
        }

        return $this->session->get(self::LOG_LEVEL_KEY, self::$defaultLevel);
    }

    public function shouldLog(LogLevel $messageLevel): bool
    {
        return $messageLevel->shouldLog($this->getLevel());
    }

    public static function setDefaultLevel(LogLevel $level): void
    {
        self::$defaultLevel = $level;
    }

    public static function getDefaultLevel(): LogLevel
    {
        return self::$defaultLevel;
    }
}
