<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server\Concerns\Capable;
use Laravel\Mcp\Server\Prompts\Argument;
use Laravel\Mcp\Server\Prompts\Arguments;

/**
 * @implements Arrayable<'name'|'description'|'title'|'arguments', string|array<int, array{name: string, description: string, required: bool}>>
 */
abstract class Prompt implements Arrayable
{
    use Capable;

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            //
        ];
    }

    /**
     * @return array{name: string, title: string, description: string, arguments: array<int, array{name: string, description: string, required: bool}>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'arguments' => array_map(
                fn (Argument $argument): array => $argument->toArray(),
                $this->arguments(),
            ),
        ];
    }
}
