<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Support\Str;
use Laravel\Mcp\Server\Annotations\Annotation;
use Laravel\Mcp\Server\Concerns\HasAnnotations;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;

abstract class Resource extends Primitive
{
    use HasAnnotations;

    protected string $uri = '';

    protected string $mimeType = '';

    public function uri(): string
    {
        if ($this instanceof HasUriTemplate) {
            return (string) $this->uriTemplate();
        }

        return $this->uri !== '' ? $this->uri : 'file://resources/'.Str::kebab(class_basename($this));
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
     *     uri?: string,
     *     uriTemplate?: string,
     *     mimeType: string,
     *     _meta?: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        $annotations = $this->annotations();

        $data = [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'mimeType' => $this->mimeType(),
        ];

        if ($annotations !== []) {
            $data['annotations'] = $annotations;
        }

        if ($this instanceof HasUriTemplate) {
            $data['uriTemplate'] = (string) $this->uriTemplate();
        } else {
            $data['uri'] = $this->uri();
        }

        // @phpstan-ignore return.type
        return $this->mergeMeta($data);
    }

    /**
     * @return array<int, class-string>
     */
    protected function allowedAnnotations(): array
    {
        return [
            Annotation::class,
        ];
    }
}
