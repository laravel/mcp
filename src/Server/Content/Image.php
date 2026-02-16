<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use InvalidArgumentException;
use Laravel\Mcp\Server\Concerns\HasMeta;
use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class Image implements Content
{
    use HasMeta;

    public function __construct(protected string $data, protected string $mimeType = 'image/png')
    {
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
        throw new InvalidArgumentException(
            'Image content may not be used in resources.',
        );
    }

    public function __toString(): string
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->mergeMeta([
            'type' => 'image',
            'data' => base64_encode($this->data),
            'mimeType' => $this->mimeType,
        ]);
    }
}
