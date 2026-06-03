<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use InvalidArgumentException;
use Laravel\Mcp\Server\Concerns\HasMeta;
use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class EmbeddedResource implements Content
{
    use HasMeta;

    /**
     * @param  array<string, mixed>  $resource
     */
    public function __construct(
        protected array $resource,
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
        throw new InvalidArgumentException(
            'EmbeddedResource content may not be used in resources.',
        );
    }

    public function __toString(): string
    {
        return (string) ($this->resource['text'] ?? $this->resource['blob'] ?? $this->resource['uri'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->mergeMeta([
            'type' => 'resource',
            'resource' => $this->resource,
        ]);
    }
}
