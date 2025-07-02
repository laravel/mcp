<?php

declare(strict_types=1);

namespace Laravel\Mcp\Resources;

use Illuminate\Contracts\Support\Arrayable;

class ResourceResult implements Arrayable
{
    public function __construct(public readonly Resource $resource) {}

    public function toArray(): array
    {
        return [
            'contents' => [
                [
                    'uri' => $this->resource->uri(),
                    'name' => $this->resource->name(),
                    'title' => $this->resource->title(),
                    'mimeType' => $this->resource->mimeType(),
                    $this->resource->type => $this->wrappedRead($this->resource),
                ],
            ],
        ];
    }

    private function wrappedRead(Resource $resource): string
    {
        return $resource->type === 'text'
            ? $resource->read()
            : base64_encode($resource->read());
    }
}
