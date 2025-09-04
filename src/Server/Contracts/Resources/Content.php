<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts\Resources;

interface Content
{
    public function toArray(): array;
}
