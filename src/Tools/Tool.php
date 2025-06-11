<?php

namespace Laravel\Mcp\Tools;

use Generator;
use Laravel\Mcp\Tools\ToolResponse;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolNotification;
use Illuminate\Support\Str;

abstract class Tool
{
    /**
     * @return string
     */
    public function name(): string
    {
        return Str::kebab(class_basename($this));
    }

    /**
     * @return string
     */
    abstract public function description(): string;

    /**
     * @param ToolInputSchema $schema
     * @return ToolInputSchema
     */
    abstract public function schema(ToolInputSchema $schema): ToolInputSchema;

    /**
     * @return ToolResponse|Generator<ToolNotification|ToolResponse>
     */
    abstract public function handle(array $arguments): ToolResponse|Generator;
}
