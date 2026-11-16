<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Skill;

#[Uri('skill://team/release-checklist/SKILL.md')]
class ReleaseChecklistSkill extends Skill
{
    public function path(): string
    {
        return __DIR__.'/Skills/release-checklist';
    }
}
