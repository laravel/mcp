<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts;

use Laravel\Mcp\Server\Completions\CompletionResult;

interface SupportsCompletion
{
    public function complete(string $argument, string $value): CompletionResult;
}
