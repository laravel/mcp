<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Support\Str;

abstract class Resource extends Primitive
{
    protected string $uri = '';

    protected string $mimeType = '';

    public function uri(): string
    {
        return $this->uri !== ''
            ? $this->uri
            : 'file://resources/'.Str::kebab(class_basename($this));
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
        return ['uri' => $this->uri()];
    }

    /**
     * @return array{
     *     name: string,
     *     title: string,
     *     description: string,
     *     uri: string,
     *     mimeType: string,
     *     _meta?: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        // @phpstan-ignore return.type
        return $this->mergeMeta([
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'uri' => $this->uri(),
            'mimeType' => $this->mimeType(),
        ]);
    }
}
