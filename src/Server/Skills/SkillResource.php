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
        protected Skill $skill,
        protected string $file,
        string $name,
        string $description,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->title = $name;
        $this->uri = $skill->fileUri($file);
    }

    public function skill(): Skill
    {
        return $this->skill;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function listed(): bool
    {
        return $this->file === 'SKILL.md';
    }

    public function source(): string
    {
        $path = $this->skill->directory()->path.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->file);

        return realpath($path) ?: $path;
    }

    public function mimeType(): string
    {
        $extension = strtolower(pathinfo($this->file, PATHINFO_EXTENSION));

        if ($extension === 'md') {
            return 'text/markdown';
        }

        return MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream';
    }

    public function handle(): Response
    {
        $contents = $this->skill->read($this->file);

        return mb_check_encoding($contents, 'UTF-8') && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $contents) !== 1
            ? Response::text($contents)
            : Response::blob($contents);
    }
}
