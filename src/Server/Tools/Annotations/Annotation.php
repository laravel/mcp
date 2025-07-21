<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tools\Annotations;

interface Annotation
{
    public function key(): string;
}
