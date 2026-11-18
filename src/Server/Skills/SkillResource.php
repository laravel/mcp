<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Skills;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Skill;
use Symfony\Component\Mime\MimeTypes;

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
        return MimeTypes::getDefault()->getMimeTypes(pathinfo($this->file, PATHINFO_EXTENSION))[0] ?? 'application/octet-stream';
    }

    public function handle(): Response
    {
        $contents = $this->skill->read($this->file);

        return mb_check_encoding($contents, 'UTF-8') ? Response::text($contents) : Response::blob($contents);
    }
}
