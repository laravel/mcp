<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tools\Annotations;

use Attribute;
use Laravel\Mcp\Server\Contracts\Tools\Annotation;

#[Attribute(Attribute::TARGET_CLASS)]
class IsDestructive implements Annotation
{
    public function __construct(public bool $value = true)
    {
        //
    }

    public function key(): string
    {
        return 'destructiveHint';
    }
}
