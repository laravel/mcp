<?php

namespace Laravel\Mcp\Tools;

use Generator;
use Illuminate\Support\Str;
use ReflectionClass;
use Illuminate\Contracts\Support\Arrayable;

abstract class Tool implements Arrayable
{
    /**
     * Get the name of the tool.
     */
    public function name(): string
    {
        return Str::kebab(class_basename($this));
    }

    /**
     * Get the description of the tool.
     */
    abstract public function description(): string;

    /**
     * Get the tool input schema.
     */
    abstract public function schema(ToolInputSchema $schema): ToolInputSchema;

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator<ToolNotification|ToolResult>
     */
    abstract public function handle(array $arguments): ToolResult|Generator;

    public function annotations(): array
    {
        $reflection = new ReflectionClass($this);

        return collect($reflection->getAttributes())
            ->map(fn ($attributeReflection) => $attributeReflection->newInstance())
            ->mapWithKeys(fn ($attribute) => [$attribute->key() => $attribute->value])
            ->all();
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'inputSchema' => $this->schema(new ToolInputSchema)->toArray(),
            'annotations' => $this->annotations() ?: (object) [],
        ];
    }
}
