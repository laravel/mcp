<?php

namespace Laravel\Mcp\Tools;

class ToolNotification
{
    public function __construct(private string $method, private array $params)
    {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function toArray(): array
    {
        return $this->params;
    }
}
