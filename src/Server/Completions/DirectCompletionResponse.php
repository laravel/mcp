<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Completions;

final class DirectCompletionResponse extends CompletionResponse
{
    public function resolve(string $value): DirectCompletionResponse
    {
        return $this;
    }
}
