<?php

declare(strict_types=1);

namespace Laravel\Mcp\Events;

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

class ToolInvoked
{
    public function __construct(
        public string $invocationId,
        public Tool $tool,
        public Request $request,
        public mixed $response,
    ) {
        //
    }
}
