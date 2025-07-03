<?php

namespace Laravel\Mcp\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Laravel\Mcp\Contracts\Resources\Content;

abstract class Resource implements Arrayable
{
    protected $content;

    abstract public function description(): string;

    abstract public function read(): string|Content;

    public function handle(): ResourceResult
    {
        $this->content = $this->content();
        $result = new ResourceResult($this);

        if ($this->content instanceof Content) {
            return $result->content($this->content);
        }

        // If binary is detected, return a blob
        return strpos($this->content, "\0") !== false
            ? $result->blob($this->content)
            : $result->text($this->content);
    }

    private function content(): string|Content
    {
        if (! isset($this->content)) {
            $this->content = $this->read();
        }

        return $this->content;
    }

    public function name(): string
    {
        return Str::kebab(class_basename($this));
    }

    public function title(): string
    {
        return Str::headline(class_basename($this));
    }

    public function uri(): string
    {
        return 'file://resources/'.Str::kebab(class_basename($this));
    }

    public function mimeType(): string
    {
        return 'text/plain';
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
