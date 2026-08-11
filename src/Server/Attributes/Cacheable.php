<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Attributes;

use Attribute;
use Laravel\Mcp\Enums\CacheScope;

#[Attribute(Attribute::TARGET_CLASS)]
class Cacheable
{
    public function __construct(
        public int $ttlMs = 0,
        public CacheScope $scope = CacheScope::Private,
    ) {
        //
    }

    /**
     * @return array{ttlMs: int, cacheScope: string}
     */
    public function toArray(): array
    {
        return [
            'ttlMs' => max(0, $this->ttlMs),
            'cacheScope' => $this->scope->value,
        ];
    }
}
