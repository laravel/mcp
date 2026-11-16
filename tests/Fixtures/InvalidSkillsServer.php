<?php

declare(strict_types=1);

namespace Tests\Fixtures;

class InvalidSkillsServer extends ExampleServer
{
    public array $skills = [__DIR__.'/Skills', __DIR__.'/MissingSkills/missing-skill'];
}
