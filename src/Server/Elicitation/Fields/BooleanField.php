<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Fields;

class BooleanField extends AbstractElicitField
{
    protected ?bool $default = null;

    public function default(bool $default): static
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
            'type' => 'boolean',
            'title' => $this->title,
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        return $schema;
    }
}
