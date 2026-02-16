<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

use Laravel\Mcp\Server\Attributes\ServerAttribute;
use ReflectionClass;

trait ReadsAttributes
{
    /**
     * @param  class-string<ServerAttribute>  $attributeClass
     */
    protected function resolveAttribute(string $attributeClass): ?string
    {
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes($attributeClass);

        return $attributes === [] ? null : $attributes[0]->newInstance()->value;
    }
}
