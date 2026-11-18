<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Skills;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Skill;

class SkillResource extends Resource
{
    public function __construct(
        public readonly Skill $skill,
        public readonly string $file,
        string $uri,
        string $name,
        string $title,
        string $description,
    ) {
        $this->uri = $uri;
        $this->name = $name;
        $this->title = $title;
        $this->description = $description;
    }

    public function mimeType(): string
    {
        if (str_ends_with($this->file, '.md')) {
            return 'text/markdown';
        }

        $mimeType = function_exists('mime_content_type')
            ? mime_content_type($this->skill->directory().DIRECTORY_SEPARATOR.$this->file)
            : false;

        return $mimeType ?: 'application/octet-stream';
    }

    public function handle(): Response
    {
        $contents = $this->skill->read($this->file);

        return mb_check_encoding($contents, 'UTF-8') ? Response::text($contents) : Response::blob($contents);
    }
}
