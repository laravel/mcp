<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server\Skill;

class TestSkill extends Skill
{
    public function __construct(
        protected string $directory = __DIR__.'/Skills/release-checklist',
        string $uri = '',
    ) {
        $this->uri = $uri;
    }

    public function path(): string
    {
        return $this->directory;
    }
}
