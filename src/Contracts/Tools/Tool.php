<?php

namespace Laravel\Mcp\Contracts\Tools;

use Generator;
use Laravel\Mcp\Tools\ToolResponse;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolNotification;

interface Tool
{
    public function getName(): string;

    public function getDescription(): string;

    public function getInputSchema(ToolInputSchema $schema): ToolInputSchema;

    /**
     * @return ToolResponse|Generator<ToolNotification|ToolResponse>
     */
    public function call(array $arguments);
}
