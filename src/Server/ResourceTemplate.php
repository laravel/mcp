<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Laravel\Mcp\Support\UriTemplate;

abstract class ResourceTemplate extends Resource
{
    abstract public function uriTemplate(): UriTemplate;

    public function uri(): string
    {
        return (string) $this->uriTemplate();
    }

    /**
     * @return array{
     *     name: string,
     *     title: string,
     *     description: string,
     *     uri?: string,
     *     uriTemplate: string,
     *     mimeType: string,
     *     _meta?: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        // @phpstan-ignore return.type
        return $this->mergeMeta([
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'uriTemplate' => (string) $this->uriTemplate(),
            'mimeType' => $this->mimeType(),
        ]);
    }
}
