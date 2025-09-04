<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Generator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolNotification;
use Laravel\Mcp\Server\Tools\ToolResult;
use ReflectionClass;

abstract class Tool implements Arrayable
{
    protected string $description;

    public function name(): string
    {
        return Str::kebab(class_basename($this));
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema;
    }

    public function description(): string
    {
        return $this->description;
    }

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

    public function shouldRegister(): bool
    {
        return true;
    }
}
