<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Fields;

class NumberField extends AbstractElicitField
{
    protected int|float|null $minimum = null;

    protected int|float|null $maximum = null;

    protected int|float|null $default = null;

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

        return $schema;
    }
}
