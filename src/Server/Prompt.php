<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Prompts\Argument;
use Laravel\Mcp\Server\Prompts\Arguments;
use Laravel\Mcp\Server\Prompts\PromptResult;

abstract class Prompt implements Arrayable
{
    protected string $description;

    abstract public function handle(array $arguments): PromptResult;

    public function arguments(): Arguments
    {
        return (new Arguments)->add(
            new Argument(
                name: 'best_cheese',
                description: 'The best cheese',
                required: false,
            ),
        );
    }

    public function description(): string
    {
        return $this->description;
    }

    public function name(): string
    {
        return Str::kebab(class_basename($this));
    }

    public function title(): string
    {
        return Str::headline(class_basename($this));
    }

    /**
     * Returned in ListPrompts
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'arguments' => $this->arguments()->toArray(),
        ];
    }
}
