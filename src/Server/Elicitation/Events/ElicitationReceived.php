<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Events;

class ElicitationReceived
{
    public function __construct(
        public readonly string $action,
        public readonly string $requestId,
        public readonly bool $hasContent,
    ) {}
}
