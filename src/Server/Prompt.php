<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Support\Str;
use Laravel\Mcp\Server\Prompts\Arguments;
use Laravel\Mcp\Server\Prompts\PromptResult;

abstract class Prompt
{
    protected string $description;

    abstract public function handle(Arguments $arguments): PromptResult;

    public function arguments(): Arguments
    {
        return [
            ['name' => 'best_cheese', 'description' => 'The best cheese', 'required' => false],
        ];
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

    protected array $arguments;
}
