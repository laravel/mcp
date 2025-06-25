<?php

namespace Laravel\Mcp\Tools\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class IsOpenWorld
{
    public function __construct(public bool $value = true) {}
}
