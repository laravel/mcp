<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use InvalidArgumentException;
use Laravel\Mcp\Schema\Icon;
use Laravel\Mcp\Server\Concerns\HasMeta;
use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class ResourceLink implements Content
{
    use HasMeta;

    /**
     * @param  array<string, mixed>  $annotations
     * @param  list<Icon>  $icons
     */
    public function __construct(
        protected string $uri,
        protected string $name,
        protected ?string $mimeType = null,
        protected ?string $title = null,
        protected ?string $description = null,
        protected ?int $size = null,
        protected array $annotations = [],
        protected array $icons = [],
    ) {}

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
        throw new InvalidArgumentException(
            'ResourceLink content may not be used in resources.',
        );
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
        $data = array_filter([
            'type' => 'resource_link',
            'uri' => $this->uri,
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
        ], fn (mixed $value): bool => $value !== null);

        if ($this->annotations !== []) {
            $data['annotations'] = $this->annotations;
        }

        if ($this->icons !== []) {
            $data['icons'] = array_map(fn (Icon $icon): array => $icon->toArray(), $this->icons);
        }

        return $this->mergeMeta($data);
    }
}
