<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Contracts\Resources\Content;
use Laravel\Mcp\Server\Resources\ResourceResult;

abstract class Resource implements Arrayable
{
    protected string $description = '';

    protected $content;

    abstract public function read(): string|Content;

    public function description(): string
    {
        return $this->description;
    }

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

    private function isBinary(string $content): bool
    {
        return strpos($content, "\0") !== false;
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
