<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts\Tools;

interface Content
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
