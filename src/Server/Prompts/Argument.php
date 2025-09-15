<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Prompts;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class Argument implements Arrayable
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $required = false,
    ) {
        //
    }

    /**
     * @return array{name: string, description: string, required: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'required' => $this->required,
        ];
    }
}
