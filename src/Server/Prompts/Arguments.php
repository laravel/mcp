<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Prompts;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<int, mixed>
 */
class Arguments implements Arrayable
{
    /**
     * @param  array<int, Argument>  $arguments
     */
    public function __construct(protected array $arguments = [])
    {
        //
    }

    public function add(Argument $argument): static
    {
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        return $this->arguments;
    }
}
