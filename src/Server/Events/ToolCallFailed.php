<?php

namespace Laravel\Mcp\Server\Events;

use Throwable;

class ToolCallFailed
{
    /**
     * Create a new event instance.
     *
     * @param  string  $toolName
     * @param  array  $arguments
     * @param  Throwable  $exception
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
        public Throwable $exception,
    ) {}
}
