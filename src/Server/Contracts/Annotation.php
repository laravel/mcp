<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts;

/**
 * @property mixed $value
 */
interface Annotation
{
    public function key(): string;

    /**
     * @return array<int, class-string>
     */
    public function allowedOn(): array;
}
