<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

use ReflectionClass;

trait ReadsAttributes
{
    protected function resolveAttribute(string $attributeClass): mixed
    {
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes($attributeClass);

        return $attributes === [] ? null : $attributes[0]->newInstance()->value; // @phpstan-ignore property.notFound
    }
}
