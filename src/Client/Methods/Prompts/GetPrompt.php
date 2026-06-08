<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods\Prompts;

use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Client\Schema\PromptResult;

/**
 * @implements Method<PromptResult>
 */
class GetPrompt implements Method
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        protected string $name,
        protected array $arguments = [],
    ) {
        //
    }

    public function method(): string
    {
        return 'prompts/get';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [
            'name' => $this->name,
            'arguments' => (object) $this->arguments,
        ];
    }

    public function handle(Protocol $protocol): PromptResult
    {
        return PromptResult::from($protocol->dispatch($this));
    }
}
