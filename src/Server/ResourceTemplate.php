<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Support\Str;

abstract class ResourceTemplate extends Primitive
{
    protected string $uriTemplate = '';

    protected string $mimeType = '';

    public function uriTemplate(): string
    {
        return $this->uriTemplate !== ''
            ? $this->uriTemplate
            : 'file://resources/'.Str::kebab(class_basename($this)).'/{id}';
    }

    public function mimeType(): string
    {
        return $this->mimeType !== ''
            ? $this->mimeType
            : 'text/plain';
    }

    /**
     * @return array<string, mixed>
     */
    public function toMethodCall(): array
    {
        return ['uriTemplate' => $this->uriTemplate()];
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'uriTemplate' => $this->uriTemplate(),
            'mimeType' => $this->mimeType(),
        ];
    }
}
