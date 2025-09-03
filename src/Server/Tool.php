<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Generator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolNotification;
use Laravel\Mcp\Server\Tools\ToolResult;

abstract class Tool implements Arrayable
{
    /**
     * The tool's title.
     */
    protected string $title = '';

    /**
     * The tool's description.
     */
    protected string $description = '';

    /**
     * Whether the tool is destructive.
     */
    protected bool $destructive = false;

    /**
     * Whether the tool is idempotent.
     */
    protected bool $idempotent = false;

    /**
     * Whether the tool is read-only.
     */
    protected bool $readonly = false;

    /**
     * Whether the tool is open-world.
     */
    protected bool $openWorld = false;

    /**
     * Get the name of the tool.
     */
    public function name(): string
    {
        return Str::kebab(class_basename($this));
    }

    /**
     * Get the tool input schema.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema;
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator<ToolNotification|ToolResult>
     */
    abstract public function handle(array $arguments): ToolResult|Generator;

    public function annotations(): array
    {
        return collect([
            'title' => 'title',
            'readonly' => 'readOnlyHint',
            'destructive' => 'destructiveHint',
            'idempotent' => 'idempotentHint',
            'openWorld' => 'openWorldHint',
        ])->filter(fn (string $annotation, string $property) => property_exists($this, $property))
            ->mapWithKeys(fn (string $annotation, string $property) => [$annotation => $this->$property])
            ->reject(fn ($value) => $value === '' || $value === false)
            ->all();
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description,
            'inputSchema' => $this->schema(new ToolInputSchema)->toArray(),
            'annotations' => $this->annotations() ?: (object) [],
        ];
    }

    public function shouldRegister(): bool
    {
        return true;
    }
}
