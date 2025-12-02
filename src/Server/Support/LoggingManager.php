<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Support;

use Laravel\Mcp\Enums\LogLevel;

class LoggingManager
{
    protected const LOG_LEVEL_KEY = 'log_level';

    private const DEFAULT_LEVEL = LogLevel::Info;

    public function __construct(protected SessionStore $session)
    {
        //
    }

    public function setLevel(LogLevel $level): void
    {
        $this->session->set(self::LOG_LEVEL_KEY, $level);
    }

    public function getLevel(): LogLevel
    {
        if (is_null($this->session->sessionId())) {
            return self::DEFAULT_LEVEL;
        }

        return $this->session->get(self::LOG_LEVEL_KEY, self::DEFAULT_LEVEL);
    }

    public function shouldLog(LogLevel $messageLevel): bool
    {
        return $messageLevel->shouldLog($this->getLevel());
    }
}
