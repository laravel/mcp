<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Prompts;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class Arguments implements Arrayable
{
    protected Collection $arguments;

    public function __construct(array $arguments = [])
    {
        $this->arguments = collect($arguments);
    }

    public function add(Argument $argument): self
    {
        $this->arguments->push($argument);

        return $this;
    }

    public function toArray(): array
    {
        return $this->arguments->toArray();
    }
}
