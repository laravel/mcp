<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Completions;

final class DirectCompletionResult extends CompletionResult
{
    public function resolve(string $value): DirectCompletionResult
    {
        return $this;
    }
}
