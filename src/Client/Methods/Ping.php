<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods;

use Laravel\Mcp\Client\Contracts\Method;

class Ping implements Method
{
    public function method(): string
    {
        return 'ping';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [];
    }
}
