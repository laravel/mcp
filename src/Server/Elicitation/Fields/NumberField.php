<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Fields;

class NumberField implements ElicitField
{
    protected ?string $description = null;

    protected int|float|null $minimum = null;

    protected int|float|null $maximum = null;

    protected int|float|null $default = null;

    protected bool $isRequired = false;

    public function __construct(protected string $title) {}

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function min(int|float $minimum): static
    {
        $this->minimum = $minimum;

        return $this;
    }

    public function max(int|float $maximum): static
    {
        $this->maximum = $maximum;

        return $this;
    }

    public function default(int|float $default): static
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
            'type' => 'number',
            'title' => $this->title,
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
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
