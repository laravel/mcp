<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Generator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Tools\ToolNotification;
use Laravel\Mcp\Server\Tools\ToolResult;
use ReflectionClass;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class Tool implements Arrayable
{
    protected string $description;

    public function name(): string
    {
        return Str::kebab(class_basename($this));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return ToolResult|Generator<ToolNotification|ToolResult>
     */
    abstract public function handle(array $arguments): ToolResult|Generator;

    /**
     * @return array<string, mixed>
     */
    public function annotations(): array
    {
        $reflection = new ReflectionClass($this);

        return collect($reflection->getAttributes())
            ->map(fn ($attributeReflection) => $attributeReflection->newInstance())
            ->mapWithKeys(fn ($attribute): array => [$attribute->key() => $attribute->value])
            ->all();
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'inputSchema' => JsonSchema::object(
                fn (JsonSchema $schema) => $this->schema($schema),
            )->toArray(),
            'annotations' => $this->annotations() ?: (object) [],
        ];
    }

    public function shouldRegister(): bool
    {
        return true;
    }
}
