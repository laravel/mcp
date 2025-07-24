<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tools;

class ToolInputSchema
{
    /**
     * The type of the property.
     */
    public const TYPE_STRING = 'string';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_NUMBER = 'number';

    public const TYPE_BOOLEAN = 'boolean';

    /**
     * The properties of the tool.
     */
    private array $properties = [];

    /**
     * The required properties of the tool.
     */
    private array $requiredProperties = [];

    /**
     * The current property of the schema.
     */
    private ?string $currentProperty = null;

    /**
     * Add a property to the tool.
     */
    private function addProperty(string $name, string $type): self
    {
        $this->properties[$name] = [
            'type' => $type,
        ];

        $this->currentProperty = $name;

        return $this;
    }

    /**
     * Add a string property to the tool.
     */
    public function string(string $name): self
    {
        return $this->addProperty($name, self::TYPE_STRING);
    }

    /**
     * Add an integer property to the tool.
     */
    public function integer(string $name): self
    {
        return $this->addProperty($name, self::TYPE_INTEGER);
    }

    /**
     * Add a number property to the tool.
     */
    public function number(string $name): self
    {
        return $this->addProperty($name, self::TYPE_NUMBER);
    }

    /**
     * Add a boolean property to the tool.
     */
    public function boolean(string $name): self
    {
        return $this->addProperty($name, self::TYPE_BOOLEAN);
    }

    /**
     * Add a description to the current property.
     */
    public function description(string $description): self
    {
        if ($this->currentProperty) {
            $this->properties[$this->currentProperty]['description'] = $description;
        }

        return $this;
    }

    /**
     * Mark the current property as required.
     */
    public function required(bool $required = true): self
    {
        if (! $this->currentProperty) {
            return $this;
        }

        $currentlyRequired = in_array($this->currentProperty, $this->requiredProperties, true);
        if ($required && $currentlyRequired) {
            return $this;
        }

        if ($required) {
            $this->requiredProperties[] = $this->currentProperty;

            return $this;
        }

        if ($currentlyRequired) {
            $this->requiredProperties = array_filter($this->requiredProperties, function ($property) {
                return $property !== $this->currentProperty;
            });
        }

        return $this;
    }

    /**
     * Mark the current property as optional.
     */
    public function optional(): self
    {
        return $this;
    }

    /**
     * Add a raw property with custom schema definition.
     */
    public function raw(string $name, array $schema): self
    {
        $this->properties[$name] = $schema;
        $this->currentProperty = $name;

        return $this;
    }

    /**
     * Convert the schema to an array.
     */
    public function toArray(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => $this->properties ?: (object) [],
        ];

        if (! empty($this->requiredProperties)) {
            $schema['required'] = $this->requiredProperties;
        }

        return $schema;
    }
}
