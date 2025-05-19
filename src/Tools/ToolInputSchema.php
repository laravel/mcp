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

    public function addProperty(string $name, string $type, string $description, bool $isRequired = false): self
    {
        $this->properties[$name] = [
            'type' => $type,
            'description' => $description,
        ];

        if ($isRequired) {
            if (!in_array($name, $this->requiredProperties)) {
                $this->requiredProperties[] = $name;
            }
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
