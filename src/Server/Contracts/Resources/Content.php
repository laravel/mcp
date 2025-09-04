<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts\Resources;

interface Content
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
