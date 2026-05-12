<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Concerns\HasAnnotations;
use Laravel\Mcp\Server\Tools\Annotations\ToolAnnotation;
use Laravel\Mcp\Server\Ui\Enums\Visibility;

abstract class Tool extends Primitive
{
    use HasAnnotations;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Define the output schema for this tool's results.
     *
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toMethodCall(): array
    {
        return ['name' => $this->name()];
    }

    /**
     * Get the tool's array representation.
     *
     * @return array{
     *     name: string,
     *     title?: string|null,
     *     description?: string|null,
     *     inputSchema?: array<string, mixed>,
     *     outputSchema?: array<string, mixed>,
     *     annotations?: array<string, mixed>|object,
     *     _meta?: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        $annotations = $this->annotations();

        $schema = JsonSchemaFactory::object(
            $this->schema(...),
        )->toArray();

        $outputSchema = JsonSchemaFactory::object(
            $this->outputSchema(...),
        )->toArray();

        $schema['properties'] ??= (object) [];

        $result = [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $schema,
            'annotations' => $annotations === [] ? (object) [] : $annotations,
        ];

        if (isset($outputSchema['properties'])) {
            $result['outputSchema'] = $outputSchema;
        }

        $rendersApp = $this->resolveAttribute(RendersApp::class);

        if ($rendersApp !== null) {
            /** @var AppResource $appResource */
            $appResource = Container::getInstance()->make($rendersApp->resource);

            $this->setMeta('ui', [
                'resourceUri' => $appResource->uri(),
                'visibility' => array_map(fn (Visibility $visiblity) => $visiblity->value, $rendersApp->visibility),
            ]);
        }

        // @phpstan-ignore return.type
        return $this->mergeMeta($result);
    }

    /**
     * @return array<int, class-string>
     */
    protected function allowedAnnotations(): array
    {
        return [
            ToolAnnotation::class,
        ];
    }
}
