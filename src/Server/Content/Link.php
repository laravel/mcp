<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class Link implements Content
{
    public function __construct(
        protected string $text,
        protected string $href,
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
        return [
            'text' => $this->text,
            'href' => $this->href,
            'uri' => $resource->uri(),
            'name' => $resource->name(),
            'title' => $resource->title(),
            'mimeType' => $resource->mimeType(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'link',
            'text' => $this->text,
            'href' => $this->href,
        ];
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
