<?php

namespace Laravel\Mcp\Tools;

use Generator;
use Illuminate\Support\Str;
use Laravel\Mcp\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Tools\Annotations\Title;
use ReflectionClass;

abstract class Tool
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
        $attributes = collect($reflection->getAttributes())
            ->mapWithKeys(fn ($attribute) => [
                $attribute->getName() => $attribute->newInstance(),
            ]);

        $readOnly = $attributes->get(IsReadOnly::class)?->value ?? false;
        $destructive = $attributes->get(IsDestructive::class)?->value ?? true;
        $idempotent = $attributes->get(IsIdempotent::class)?->value ?? false;
        $openWorld = $attributes->get(IsOpenWorld::class)?->value ?? true;
        $title = $attributes->get(Title::class)?->value ?? Str::headline(class_basename($this));

        return [
            'title' => $title,
            'readOnlyHint' => $readOnly,
            'destructiveHint' => $readOnly ? false : $destructive,
            'idempotentHint' => $readOnly ? false : $idempotent,
            'openWorldHint' => $openWorld,
        ];
    }
}
