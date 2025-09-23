<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Annotations;

use Attribute;
use Laravel\Mcp\Server\Contracts\Tools\Annotation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class RequiresAbility implements Annotation
{
    /**
     * @param  array<int, string>|string  $abilities
     */
    public function __construct(public array|string $abilities)
    {
        //
    }

    public function key(): string
    {
        return 'required_abilities';
    }

    /**
     * Magic property used by Tool::annotations() mapping.
     *
     * @return array<int, string>
     */
    public function __get(string $name): mixed
    {
        if ($name === 'value') {
            return is_array($this->abilities) ? array_values($this->abilities) : [$this->abilities];
        }

        // For unknown properties, return empty list to satisfy type expectations
        return [];
    }
}
