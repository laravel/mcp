<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Skills;

use Laravel\Mcp\Server\Skill;

class DirectorySkill extends Skill
{
    public function __construct(protected string $path)
    {
        //
    }

    public function path(): string
    {
        return $this->path;
    }
}
