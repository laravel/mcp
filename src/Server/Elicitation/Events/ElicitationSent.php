<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Events;

class ElicitationSent
{
    public function __construct(
        public readonly string $mode,
        public readonly string $message,
        public readonly string $requestId,
    ) {}
}
