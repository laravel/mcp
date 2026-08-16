<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Server\Attributes\Cacheable;
use Laravel\Mcp\Server\Resource;

#[Cacheable(ttlMs: 120000, scope: CacheScope::Public)]
class CacheableResource extends Resource
{
    public function description(): string
    {
        return 'A resource that declares its own caching hint';
    }

    public function handle(): string
    {
        return 'Cacheable contents.';
    }
}
