<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Support\Str;
use Laravel\Mcp\Server\Contracts\Resources\Content;
use Laravel\Mcp\Server\Resources\ResourceResult;

abstract class Resource extends Primitive
{
    protected string $uri = '';

    protected string $mimeType = '';

    protected string|Content $content;

    abstract public function read(): string|Content;

    public function handle(): ResourceResult
    {
        $this->content = $this->content();

        $result = new ResourceResult($this);

        if ($this->content instanceof Content) {
            return $result->content($this->content);
        }

        return $this->isBinary($this->content)
            ? $result->blob($this->content)
            : $result->text($this->content);
    }

    protected function isBinary(string $content): bool
    {
        return str_contains($content, "\0");
    }

    protected function content(): string|Content
    {
        if (! isset($this->content)) {
            $this->content = $this->read();
        }

        return $this->content;
    }

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

    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'uri' => $this->uri(),
            'mimeType' => $this->mimeType(),
        ];
    }
}
