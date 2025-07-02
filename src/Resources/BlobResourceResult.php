<?php

declare(strict_types=1);

namespace Laravel\Mcp\Resources;

class BlobResourceResult extends ResourceResult
{
    public function toArray(): array
    {
        return [
            'contents' => [
                [
                    'uri' => $this->resource->uri(),
                    'name' => $this->resource->name(),
                    'title' => $this->resource->title(),
                    'mimeType' => $this->resource->mimeType(),
                    'blob' => base64_encode($this->resource->read()),
                ],
            ],
        ];
    }
}
