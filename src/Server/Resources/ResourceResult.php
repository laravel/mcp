<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server\Contracts\Resources\Content;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Resources\Content\Blob;
use Laravel\Mcp\Server\Resources\Content\Text;

class ResourceResult implements Arrayable
{
    /** @var array<\Laravel\Mcp\Server\Contracts\Resources\Content> */
    private array $contents = [];

    public function __construct(public readonly Resource $resource) {}

    public function content(Content $content): self
    {
        $this->contents[] = $content;

        return $this;
    }

    public function blob(string $content): self
    {
        return $this->content(new Blob($content));
    }

    public function text(string $content): self
    {
        return $this->content(new Text($content));
    }

    public function toArray(): array
    {
        return [
            'contents' => collect($this->contents)
                ->map(fn (Content $item) => array_merge([
                    'uri' => $this->resource->uri(),
                    'name' => $this->resource->name(),
                    'title' => $this->resource->title(),
                    'mimeType' => $this->resource->mimeType(),
                ], $item->toArray()))
                ->all(),
        ];
    }
}
