<?php

namespace Laravel\Mcp\Tools;

use Generator;
use Illuminate\Support\Str;

abstract class Tool
{
    /**
     * Get the name of the tool.
     */
    public function name(): string
    {
        return Str::kebab(class_basename($this));
    }

    /**
     * Get the description of the tool.
     */
    abstract public function description(): string;

    /**
     * Get the tool input schema.
     */
    abstract public function schema(ToolInputSchema $schema): ToolInputSchema;

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator<ToolNotification|ToolResult>
     */
    abstract public function handle(array $arguments): ToolResult|Generator;
}
