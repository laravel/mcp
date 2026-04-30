<?php

declare(strict_types=1);

namespace Laravel\Mcp\Events;

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

class InvokingTool
{
    public function __construct(
        public string $invocationId,
        public Tool $tool,
        public Request $request,
    ) {
        //
    }
}
