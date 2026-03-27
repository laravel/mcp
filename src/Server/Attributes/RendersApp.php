<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RendersApp
{
    /**
     * @param  class-string  $resource
     * @param  array<int, string>  $visibility
     */
    public function __construct(
        public string $resource,
        public array $visibility = ['model', 'app'],
    ) {
        //
    }
}
