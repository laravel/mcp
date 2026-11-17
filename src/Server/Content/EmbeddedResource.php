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
     * @param  array<string, mixed>  $annotations
     */
    public function __construct(
        protected array $resource,
        protected array $annotations = [],
    ) {
        if (! is_string($resource['uri'] ?? null)) {
            throw new InvalidArgumentException('Embedded resource contents require a [uri].');
        }

        if (array_key_exists('text', $resource) === array_key_exists('blob', $resource)) {
            throw new InvalidArgumentException('Embedded resource contents require either [text] or [blob], but not both.');
        }
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
            'EmbeddedResource content may not be used in resources.',
        );
    }

    public function __toString(): string
    {
        return (string) ($this->resource['text'] ?? $this->resource['uri']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type' => 'resource',
            'resource' => $this->resource,
        ];

        if ($this->annotations !== []) {
            $data['annotations'] = $this->annotations;
        }

        return $this->mergeMeta($data);
    }
}
