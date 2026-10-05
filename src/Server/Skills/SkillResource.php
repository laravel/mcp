<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Skills;

use finfo;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Skill;

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

    public function mimeType(): string
    {
        if (strtolower(pathinfo($this->file, PATHINFO_EXTENSION)) === 'md') {
            return 'text/markdown';
        }

        return (new finfo(FILEINFO_MIME_TYPE))->buffer($this->skill->read($this->file)) ?: 'application/octet-stream';
    }

    public function sourcePath(): string
    {
        return $this->skill->path().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->file);
    }

    public function handle(): Response
    {
        $contents = $this->skill->read($this->file);

        return mb_check_encoding($contents, 'UTF-8') && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $contents) !== 1
            ? Response::text($contents)
            : Response::blob($contents);
    }
}
