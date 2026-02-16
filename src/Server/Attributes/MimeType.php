<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class MimeType
{
    public function __construct(public string $value) {}
}
