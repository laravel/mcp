<?php

namespace Laravel\Mcp\Server\Events;

class ToolCallFinished
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
    ) {}
}
