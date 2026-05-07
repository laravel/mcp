<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use InvalidArgumentException;
use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Server\Annotations\Audience;
use Laravel\Mcp\Server\Annotations\LastModified;
use Laravel\Mcp\Server\Annotations\Priority;
use Laravel\Mcp\Server\Concerns\HasMeta;
use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class ResourceLink implements Content
{
    use HasMeta;

    /**
     * @var array<string, mixed>
     */
    protected array $annotations = [];

    public function __construct(
        protected string $uri,
        protected ?string $name = null,
        protected ?string $title = null,
        protected ?string $description = null,
        protected ?string $mimeType = null,
        protected ?int $size = null,
    ) {}

    /**
     * @param  Role|array<int, Role>  $roles
     */
    public function audience(Role|array $roles): self
    {
        $this->annotations['audience'] = (new Audience($roles))->value;

        return $this;
    }

    public function priority(float $priority): self
    {
        $this->annotations['priority'] = (new Priority($priority))->value;

        return $this;
    }

    public function lastModified(string $timestamp): self
    {
        $this->annotations['lastModified'] = (new LastModified($timestamp))->value;

        return $this;
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

        return $this->mergeMeta($data);
    }
}
