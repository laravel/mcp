<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods;

use Laravel\Mcp\Client\Contracts\Method;

class ListTools implements Method
{
    public function method(): string
    {
        return 'tools/list';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [];
    }
}
