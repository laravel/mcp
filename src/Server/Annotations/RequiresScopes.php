<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Annotations;

use Attribute;
use Laravel\Mcp\Server\Contracts\Tools\Annotation;

#[Attribute(Attribute::TARGET_CLASS)]
class RequiresScopes implements Annotation
{
    /**
     * @param  array<int, string>|string  $scopes
     */
    public function __construct(public array|string $scopes)
    {
        //
    }

    public function key(): string
    {
        return 'required_scopes';
    }

    /**
     * Magic property used by Tool::annotations() mapping.
     * ALL semantics: all listed scopes are required.
     *
     * @return array<int, string>
     */
    public function __get(string $name): mixed
    {
        if ($name === 'value') {
            return is_array($this->scopes) ? array_values($this->scopes) : [$this->scopes];
        }

        // For unknown properties, return empty list to satisfy type expectations
        return [];
    }
}
