<?php

declare(strict_types=1);

namespace Tests\Fixtures;

class SkillsServer extends ExampleServer
{
    public array $skills = [TestSkill::class];
}
