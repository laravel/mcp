<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use Laravel\Mcp\Server\Enums\LogLevel;

class LogNotification extends Notification
{
    public function __construct(
        protected LogLevel $level,
        protected mixed $data,
        protected ?string $logger = null,
    ) {
        parent::__construct('notifications/message', $this->buildParams());
    }

    public function level(): LogLevel
    {
        return $this->level;
    }

    public function data(): mixed
    {
        return $this->data;
    }

    public function logger(): ?string
    {
        return $this->logger;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildParams(): array
    {
        $params = [
            'level' => $this->level->value,
            'data' => $this->data,
        ];

        if ($this->logger !== null) {
            $params['logger'] = $this->logger;
        }

        return $params;
    }
}
