<?php

namespace Laravel\Mcp\Tools\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Title
{
    public function __construct(public string $value) {}
}
