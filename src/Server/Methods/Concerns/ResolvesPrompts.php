<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods\Concerns;

use InvalidArgumentException;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\ServerContext;

trait ResolvesPrompts
{
    protected function resolvePrompt(?string $name, ServerContext $context): Prompt
    {
        if (! $name) {
            throw new InvalidArgumentException('Missing [name] parameter.');
        }

        return $context->prompts()->first(
            fn ($prompt): bool => $prompt->name() === $name,
            fn () => throw new InvalidArgumentException("Prompt [{$name}] not found.")
        );
    }
}
