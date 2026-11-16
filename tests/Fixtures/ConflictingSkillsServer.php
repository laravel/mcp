<?php

declare(strict_types=1);

namespace Tests\Fixtures;

class ConflictingSkillsServer extends ExampleServer
{
    public array $skills = [__DIR__.'/Skills', __DIR__.'/Skills/release-checklist'];
}
