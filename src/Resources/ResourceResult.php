<?php

declare(strict_types=1);

namespace Laravel\Mcp\Resources;

use Illuminate\Contracts\Support\Arrayable;

class ResourceResult implements Arrayable
{
    public function __construct(public readonly Resource $resource) {}

    public function toArray(): array
    {
        $key = $this->resource instanceof BlobResource ? 'blob' : 'text';
        $content = ($this->resource instanceof BlobResource) ? base64_encode($this->resource->read()) : $this->resource->read();

        return [
            'contents' => [
                [
                    'uri' => $this->resource->uri(),
                    'name' => $this->resource->name(),
                    'title' => $this->resource->title(),
                    'mimeType' => $this->resource->mimeType(),
                    $key => $content,
                ],
            ],
        ];
    }
}
