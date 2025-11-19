<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Annotations;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
class Priority extends Annotation
{
    public function __construct(public float $value)
    {
        if ($value < 0.0 || $value > 1.0) {
            throw new InvalidArgumentException(
                "Priority must be between 0.0 and 1.0, got {$value}"
            );
        }
    }

    public function key(): string
    {
        return 'priority';
    }
}
