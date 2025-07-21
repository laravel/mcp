<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tools;

use Illuminate\Contracts\Support\Arrayable;

class ToolNotification implements Arrayable
{
    /**
     * Create a new tool notification response.
     */
    public function __construct(private string $method, private array $params) {}

    /**
     * Get the method (name) of the notification.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Convert the notification response to an array.
     */
    public function toArray(): array
    {
        return $this->params;
    }
}
