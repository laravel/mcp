<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Prompts;

use Illuminate\Contracts\Support\Arrayable;

class Arguments implements Arrayable
{
    public function toArray(): array
    {
        return $this->arguments;
    }
}
