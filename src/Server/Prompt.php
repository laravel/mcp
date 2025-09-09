<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Prompts\Arguments;

/**
 * @implements Arrayable<'name'|'description'|'title'|'arguments', string|array<int, array{name: string, description: string, required: bool}>>
 */
abstract class Prompt implements Arrayable
{
    protected string $description;

    public function arguments(): Arguments
    {
        return new Arguments([
            //
        ]);
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
     * @return array{name: string, description: string, title: string, arguments: array<int, array{name: string, description: string, required: bool}>}
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
