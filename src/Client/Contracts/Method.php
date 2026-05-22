<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Contracts;

use Laravel\Mcp\Client\Protocol;

interface Method
{
    public function method(): string;

    /**
     * @return array<string, mixed>
     */
    public function params(): array;

    /**
     * @return mixed
     */
    public function handle(Protocol $protocol);
}
