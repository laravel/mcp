<?php

namespace Laravel\Mcp\Tools\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class IsDestructive
{
    public function __construct(public bool $value = true) {}
}
