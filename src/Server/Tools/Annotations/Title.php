<?php

namespace Laravel\Mcp\Server\Tools\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Title implements Annotation
{
    public function __construct(public string $value) {}

    public function key(): string
    {
        return 'title';
    }
}
