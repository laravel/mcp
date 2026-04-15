<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Concerns\HasMeta;
use Laravel\Mcp\Server\Contracts\Content;

class ResourceLink implements Content
{
    use HasMeta;

    public function __construct(
        protected string $uri,
        protected ?string $name = null,
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
        return $this->toArray();
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
            'description' => $this->description,
            'mimeType' => $this->mimeType,
        ], fn ($value) => $value !== null);

        return $this->mergeMeta($data);
    }
}
