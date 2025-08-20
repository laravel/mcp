<?php

namespace Laravel\Mcp\Server\Events;

class ToolCallStarting
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
    ) {}
}
