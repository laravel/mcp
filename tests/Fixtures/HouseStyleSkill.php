<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\Config\Repository;
use Laravel\Mcp\Server\Skill;

class HouseStyleSkill extends Skill
{
    public function path(): string
    {
        return __DIR__.'/Skills/house-style';
    }

    public function shouldRegister(Repository $config): bool
    {
        return $config->get('mcp.testing.house-style', true);
    }
}
