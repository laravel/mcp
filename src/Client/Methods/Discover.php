<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods;

use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Client\Schema\DiscoverResult;

/**
 * @implements Method<DiscoverResult>
 */
class Discover implements Method
{
    public function method(): string
    {
        return 'server/discover';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [];
    }

    public function handle(Protocol $protocol): DiscoverResult
    {
        return DiscoverResult::from($protocol->dispatch($this));
    }
}
