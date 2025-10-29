<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class StructuredContent implements Content
{
    /**
     * @param  array<string, mixed>|object  $structuredContent
     */
    public function __construct(protected array|object $structuredContent = [])
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toTool(Tool $tool): array
    {
        return json_decode($this->toJsonString(), true);
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
            'json' => $this->toJsonString(),
            'uri' => $resource->uri(),
            'name' => $resource->name(),
            'title' => $resource->title(),
            'mimeType' => $resource->mimeType() === 'text/plain'
                ? 'application/json'
                : $resource->mimeType(),
        ];
    }

    public function __toString(): string
    {
        return $this->toJsonString();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'text',
            'text' => $this->toJsonString(),
        ];
    }

    private function toJsonString(): string
    {
        return json_encode($this->structuredContent, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
