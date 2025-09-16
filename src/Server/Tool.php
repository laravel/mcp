<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Contracts\Tools\Annotation;
use ReflectionAttribute;
use ReflectionClass;

abstract class Tool extends Primitive
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
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
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => JsonSchema::object(
                fn (JsonSchema $schema): array => $this->schema($schema),
            )->toArray(),
            'annotations' => $annotations === [] ? (object) [] : $annotations,
        ];
    }
}
