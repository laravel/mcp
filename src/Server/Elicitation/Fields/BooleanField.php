<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Fields;

class BooleanField implements ElicitField
{
    protected ?string $description = null;

    protected ?bool $default = null;

    protected bool $isRequired = false;

    public function __construct(protected string $title) {}

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function default(bool $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function required(): static
    {
        $this->isRequired = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schema = [
            'type' => 'boolean',
            'title' => $this->title,
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        if ($this->isRequired) {
            $schema['_required'] = true;
        }

        return $schema;
    }
}
