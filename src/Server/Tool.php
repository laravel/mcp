<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Contracts\Tools\Annotation;
use ReflectionAttribute;
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
     * @return array<string, mixed>
     */
    public function annotations(): array
    {
        $reflection = new ReflectionClass($this);

        // @phpstan-ignore-next-line
        return collect($reflection->getAttributes())
            ->map(fn (ReflectionAttribute $attributeReflection): object => $attributeReflection->newInstance())
            // @phpstan-ignore-next-line
            ->mapWithKeys(fn (Annotation $attribute): array => [$attribute->key() => $attribute->value])
            ->all();
    }

    public function toArray(): array
    {
        $annotations = $this->annotations();

        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'inputSchema' => JsonSchema::object(
                fn (JsonSchema $schema): array => $this->schema($schema),
            )->toArray(),
            'annotations' => $annotations === [] ? (object) [] : $annotations,
        ];
    }
}
