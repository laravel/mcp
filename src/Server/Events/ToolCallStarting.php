<?php

namespace Laravel\Mcp\Server\Events;

class ToolCallStarting
{
    /**
     * Create a new event instance.
     *
     * @param  string  $toolName
     * @param  array  $arguments
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
    ) {}
}
