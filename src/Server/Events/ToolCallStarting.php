<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Events;

class ToolCallStarting
{
    /**
     * Create a new event instance.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
    ) {}
}
