<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tools\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class IsReadOnly implements Annotation
{
    public function __construct(public bool $value = true) {}

    public function key(): string
    {
        return 'readOnlyHint';
    }
}
