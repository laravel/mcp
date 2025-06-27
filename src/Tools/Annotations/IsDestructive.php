<?php

namespace Laravel\Mcp\Tools\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class IsDestructive implements Annotation
{
    public function __construct(public bool $value = true) {}

    public function key(): string
    {
        return 'destructiveHint';
    }
}
