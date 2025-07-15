<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Prompts;

use Illuminate\Contracts\Support\Arrayable;

class Argument implements Arrayable
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $required = false,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'required' => $this->required,
        ];
    }
}
