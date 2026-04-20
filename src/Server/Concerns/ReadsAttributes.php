<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

use ReflectionClass;

trait ReadsAttributes
{
    /**
     * @var array<string, object|null>
     */
    protected static array $attributeCache = [];

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attributeClass
     * @return T|null
     */
    protected function resolveAttribute(string $attributeClass): mixed
    {
        $cacheKey = static::class.'@'.$attributeClass;

        if (array_key_exists($cacheKey, static::$attributeCache)) {
            return static::$attributeCache[$cacheKey]; // @phpstan-ignore return.type
        }

        $reflection = new ReflectionClass($this);

        do {
            $attributes = $reflection->getAttributes($attributeClass);

            if ($attributes !== []) {
                return static::$attributeCache[$cacheKey] = $attributes[0]->newInstance();
            }
        } while ($reflection = $reflection->getParentClass());

        return static::$attributeCache[$cacheKey] = null;
    }
}
