<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Annotations;

use Laravel\Mcp\Server\Contracts\Annotation as AnnotationContract;
use Laravel\Mcp\Server\Resource;

/**
 * @property mixed $value
 */
abstract class Annotation implements AnnotationContract
{
    /**
     * @return array<int, class-string>
     */
    public function allowedOn(): array
    {
        return [Resource::class];
    }
}
