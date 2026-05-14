<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Contracts;

interface Method
{
    public function method(): string;

    /**
     * @return array<string, mixed>
     */
    public function params(): array;
}
