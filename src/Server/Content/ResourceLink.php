<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use Laravel\Mcp\Server\Concerns\HasAnnotations;
use Laravel\Mcp\Server\Concerns\HasMeta;
use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class ResourceLink implements Content
{
    use HasAnnotations;
    use HasMeta;

    public function __construct(
        protected string $uri,
        protected string $name,
        protected ?string $mimeType = null,
        protected ?string $description = null,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toTool(Tool $tool): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPrompt(Prompt $prompt): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toResource(Resource $resource): array
    {
        return $this->mergeMeta([
            'uri' => $resource->uri(),
            'mimeType' => $resource->mimeType(),
        ]);
    }

    public function __toString(): string
    {
        return $this->uri;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type' => 'resource_link',
            'uri' => $this->uri,
            'name' => $this->name,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->mimeType !== null) {
            $data['mimeType'] = $this->mimeType;
        }

        $annotations = $this->annotations();

        if ($annotations !== []) {
            $data['annotations'] = $annotations;
        }

        return $this->mergeMeta($data);
    }

    /**
     * @return array<int, class-string>
     */
    protected function allowedAnnotations(): array
    {
        return [
            \Laravel\Mcp\Server\Annotations\Annotation::class,
        ];
    }
}
