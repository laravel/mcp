<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use Exception;
use Laravel\Mcp\Enums\OpenAI;
use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class App implements Content
{
    /**
     * @var array<string, mixed>
     */
    protected array $meta = [];

    public function __construct(
        protected string $text,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toTool(Tool $tool): array
    {
        throw new Exception('App should only be used from a Resource.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPrompt(Prompt $prompt): array
    {
        throw new Exception('App should only be used from a Resource.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toResource(Resource $resource): array
    {
        return array_filter([
            'text' => $this->text,
            'uri' => $resource->uri(),
            'name' => $resource->name(),
            'title' => $resource->title(),
            'mimeType' => $resource->mimeType(),
            '_meta' => $this->meta,
        ], filled(...));
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return ($meta is null ? array<string, mixed> : self)
     */
    public function meta(?array $meta = null): self|array
    {
        if (is_null($meta)) {
            return $this->meta;
        }

        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function prefersBorder(bool $value = true): self
    {
        $this->meta[OpenAI::WIDGET_PREFERS_BORDER->value] = $value;

        return $this;
    }

    public function widgetDescription(string $value): self
    {
        $this->meta[OpenAI::WIDGET_DESCRIPTION->value] = $value;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function widgetCSP(array $value): self
    {
        $this->meta[OpenAI::WIDGET_CSP->value] = $value;

        return $this;
    }

    public function widgetDomain(string $value): self
    {
        $this->meta[OpenAI::WIDGET_DOMAIN->value] = $value;

        return $this;
    }

    public function __toString(): string
    {
        return $this->text;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'text',
            'text' => $this->text,
        ];
    }
}
