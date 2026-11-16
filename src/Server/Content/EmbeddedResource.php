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
     * @param  array<string, mixed>  $annotations
     */
    public function __construct(
        protected string $uri,
        protected ?string $text = null,
        protected ?string $blob = null,
        protected ?string $mimeType = null,
        protected array $annotations = [],
    ) {
        if (($text === null) === ($blob === null)) {
            throw new InvalidArgumentException('Embedded resources require either text or blob content, but not both.');
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
        return $this->text ?? (string) $this->blob;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type' => 'resource',
            'resource' => array_filter([
                'uri' => $this->uri,
                'mimeType' => $this->mimeType,
                'text' => $this->text,
                'blob' => $this->blob === null ? null : base64_encode($this->blob),
            ], fn (mixed $value): bool => $value !== null),
        ];

        if ($this->annotations !== []) {
            $data['annotations'] = $this->annotations;
        }

        return $this->mergeMeta($data);
    }
}
