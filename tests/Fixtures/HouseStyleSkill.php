<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\Config\Repository;
use Laravel\Mcp\Server\Skill;

class HouseStyleSkill extends Skill
{
    protected string $name = 'house-style';

    protected string $description = 'Use when you write copy for a customer.';

    protected array $frontmatter = ['license' => 'MIT'];

    public function instructions(): string
    {
        return "# House Style\n\nBe brief.\n";
    }

    public function shouldRegister(Repository $config): bool
    {
        return $config->get('mcp.testing.house-style', true);
    }
}
