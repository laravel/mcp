<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server\Attributes\Uri;

#[Uri('skill://team/release-checklist/SKILL.md')]
class TeamReleaseChecklistSkill extends ReleaseChecklistSkill
{
    //
}
