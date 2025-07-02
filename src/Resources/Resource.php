<?php

namespace Laravel\Mcp\Resources;

use Illuminate\Support\Str;

abstract class Resource
{
    public string $type = 'text'; // 'text' or 'blob'. blob will be base64 encoded.

    abstract public function description(): string;

    abstract public function read(): string;

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
}
