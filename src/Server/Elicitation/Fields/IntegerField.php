<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Fields;

class IntegerField extends AbstractElicitField
{
    protected ?int $minimum = null;

    protected ?int $maximum = null;

    protected ?int $default = null;

    public function min(int $minimum): static
    {
        $this->minimum = $minimum;

        return $this;
    }

    public function max(int $maximum): static
    {
        $this->maximum = $maximum;

        return $this;
    }

    public function default(int $default): static
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
            'type' => 'integer',
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
