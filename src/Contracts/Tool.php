<?php

namespace Laravel\Mcp\Contracts;

interface Tool
{
    public static function getName(): string;

    public function getDescription(): string;

    public function getInputSchema(): array;

    public function call(array $arguments): array;
}
