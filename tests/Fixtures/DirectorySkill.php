<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server\Skill;

class DirectorySkill extends Skill
{
    public function __construct(protected string $skillPath, protected ?string $skillUri = null)
    {
        //
    }

    public function path(): string
    {
        return $this->skillPath;
    }

    public function uri(): string
    {
        return $this->skillUri ?? parent::uri();
    }
}
