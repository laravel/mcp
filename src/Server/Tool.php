<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Concerns\HasAnnotations;
use Laravel\Mcp\Server\Tools\Annotations\ToolAnnotation;
use Laravel\Mcp\Server\Ui\Enums\Visibility;
use Laravel\Mcp\Support\ToolHeaders;

abstract class Tool extends Primitive
{
    use HasAnnotations;

    /**
     * Argument paths to mirror into an [Mcp-Param-{name}] HTTP header, keyed by path.
     *
     * @var array<string, string>
     */
    protected array $parameterHeaders = [];

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
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function withParameterHeaders(array $schema): array
    {
        foreach ($this->parameterHeaders as $path => $header) {
            $key = 'properties.'.implode('.properties.', explode('.', $path));

            if (! Arr::has($schema, $key)) {
                throw new InvalidArgumentException("Tool [{$this->name()}] mirrors the unknown argument [{$path}] into a header.");
            }

            Arr::set($schema, $key.'.'.ToolHeaders::ANNOTATION, $header);
        }

        $reason = ToolHeaders::invalid($schema);

        if ($reason !== null) {
            throw new InvalidArgumentException("Tool [{$this->name()}] cannot mirror arguments into headers because {$reason}.");
        }

        return $schema;
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
        $schema = $this->withParameterHeaders($schema);

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
        return $this->mergeMeta($this->mergeIcons($result));
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
