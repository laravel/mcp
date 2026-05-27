<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Contracts;

use Laravel\Mcp\Client\Protocol;

/**
 * @template TResult
 */
interface Method
{
    public function method(): string;

    /**
     * @return array<string, mixed>
     */
    public function params(): array;

    /**
     * @return TResult
     */
    public function handle(Protocol $protocol);
}
