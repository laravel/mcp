<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts\Resources;

interface Content
{
    public function __construct(string $content);

    public function toArray(): array;
}
