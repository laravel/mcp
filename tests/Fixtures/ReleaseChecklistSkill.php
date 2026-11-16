<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server\Skill;

class ReleaseChecklistSkill extends Skill
{
    protected ?string $directory = __DIR__.'/Skills/release-checklist';

    protected ?string $uri = 'skill://team/release-checklist/SKILL.md';
}
