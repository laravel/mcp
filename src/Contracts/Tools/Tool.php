<?php

namespace Laravel\Mcp\Contracts\Tools;

use Laravel\Mcp\Tools\ToolResponse;
use Laravel\Mcp\Tools\ToolInputSchema;

interface Tool
{
    public function getName(): string;

    public function getDescription(): string;

    public function getInputSchema(ToolInputSchema $schema): ToolInputSchema;

    public function call(array $arguments): ToolResponse;
}
