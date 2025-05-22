<?php

namespace Laravel\Mcp\Tools;

class ToolInputSchema
{
    public const TYPE_STRING = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOLEAN = 'boolean';

    private array $properties = [];
    private array $requiredProperties = [];
    private ?string $currentProperty = null;

    private function addProperty(string $name, string $type): self
    {
        $this->properties[$name] = [
            'type' => $type,
        ];

        $this->currentProperty = $name;

        return $this;
    }

    public function string(string $name): self
    {
        return $this->addProperty($name, self::TYPE_STRING);
    }

    public function integer(string $name): self
    {
        return $this->addProperty($name, self::TYPE_INTEGER);
    }

    public function number(string $name): self
    {
        return $this->addProperty($name, self::TYPE_NUMBER);
    }

    public function boolean(string $name): self
    {
        return $this->addProperty($name, self::TYPE_BOOLEAN);
    }

    public function description(string $description): self
    {
        if ($this->currentProperty) {
            $this->properties[$this->currentProperty]['description'] = $description;
        }

        return $this;
    }

    public function required(): self
    {
        if ($this->currentProperty && ! in_array($this->currentProperty, $this->requiredProperties)) {
            $this->requiredProperties[] = $this->currentProperty;
        }

        return $this;
    }

    public function toArray(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => $this->properties,
        ];

        if (! empty($this->requiredProperties)) {
            $schema['required'] = $this->requiredProperties;
        }

        return $schema;
    }
}
