<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Annotations;

use Laravel\Mcp\Server\Contracts\Annotation;
use Laravel\Mcp\Server\Tool;

/**
 * @property mixed $value
 */
abstract class ToolAnnotation implements Annotation
{
    /**
     * @return array<int, class-string>
     */
    public function allowedOn(): array
    {
        return [Tool::class];
    }
}
