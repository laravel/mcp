<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Fields;

class StringField extends AbstractElicitField
{
    protected ?int $minLength = null;

    protected ?int $maxLength = null;

    protected ?string $pattern = null;

    protected ?string $format = null;

    protected ?string $default = null;

    public function minLength(int $minLength): static
    {
        $this->minLength = $minLength;

        return $this;
    }

    public function maxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function pattern(string $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function default(string $default): static
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
            'type' => 'string',
            'title' => $this->title,
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }

        if ($this->pattern !== null) {
            $schema['pattern'] = $this->pattern;
        }

        if ($this->format !== null) {
            $schema['format'] = $this->format;
        }

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        return $schema;
    }
}
