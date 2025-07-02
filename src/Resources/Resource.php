<?php

namespace Laravel\Mcp\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

abstract class Resource implements Arrayable
{
    abstract public function description(): string;

    abstract public function read(): string;

    public function handle(): ResourceResult
    {
        return new ResourceResult($this);
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
