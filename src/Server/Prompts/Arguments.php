<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Prompts;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * @implements Arrayable<int, mixed>
 */
class Arguments implements Arrayable
{
    /** @var Collection<int, Argument> */
    protected Collection $arguments;

    /**
     * @param  array<int, Argument>  $arguments
     */
    public function __construct(array $arguments = [])
    {
        $this->arguments = collect($arguments);
    }

    public function add(Argument $argument): static
    {
        $this->arguments->push($argument);

        return $this;
    }

    public function toArray(): array
    {
        return $this->arguments->toArray();
    }
}
