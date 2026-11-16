<?php

declare(strict_types=1);

use App\Mcp\Skills\HouseStyle;
use Illuminate\Filesystem\Filesystem;
use Laravel\Mcp\Server\Skill;

afterEach(function (): void {
    (new Filesystem)->deleteDirectory(resource_path('skills'));
});

it('creates a valid skill directory', function (): void {
    $this->artisan('make:mcp-skill', [
        'name' => 'ReleaseChecklist',
        '--description' => 'Use when: preparing a release.',
    ])->assertExitCode(0);

    $skill = Skill::fromDirectory(resource_path('skills/release-checklist'));

    expect($skill->name())->toBe('release-checklist')
        ->and($skill->description())->toBe('Use when: preparing a release.')
        ->and($skill->read('SKILL.md'))->toContain('# Release Checklist');

    $skill->validate();
});

it('uses a default description', function (): void {
    $this->artisan('make:mcp-skill', ['name' => 'hotfix'])->assertExitCode(0);

    expect(Skill::fromDirectory(resource_path('skills/hotfix'))->description())->toBe('Describe when an agent should use this skill.');
});

it('rejects invalid skill names', function (): void {
    $this->artisan('make:mcp-skill', ['name' => 'under_score'])
        ->expectsOutputToContain('must be at most 64 lowercase letters')
        ->assertExitCode(1);
});

it('does not overwrite an existing skill unless forced', function (): void {
    $this->artisan('make:mcp-skill', ['name' => 'hotfix'])->assertExitCode(0);
    file_put_contents(resource_path('skills/hotfix/SKILL.md'), 'custom');

    $this->artisan('make:mcp-skill', ['name' => 'hotfix'])
        ->expectsOutputToContain('already exists')
        ->assertExitCode(1);
    expect(file_get_contents(resource_path('skills/hotfix/SKILL.md')))->toBe('custom');

    $this->artisan('make:mcp-skill', ['name' => 'hotfix', '--force' => true])->assertExitCode(0);
    expect(file_get_contents(resource_path('skills/hotfix/SKILL.md')))->toStartWith('---');
});

it('may publish a custom stub', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'mcp-stubs',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(base_path('stubs/mcp-skill.stub'));
    $this->assertFileExists(base_path('stubs/mcp-skill.class.stub'));
    @unlink(base_path('stubs/mcp-skill.class.stub'));

    file_put_contents(base_path('stubs/mcp-skill.stub'), "---\nname: {{ name }}\ndescription: {{ description }}\n---\n\nCustom stub.\n");

    try {
        $this->artisan('make:mcp-skill', ['name' => 'custom'])->assertExitCode(0);
        expect(file_get_contents(resource_path('skills/custom/SKILL.md')))->toContain('Custom stub.');
    } finally {
        @unlink(base_path('stubs/mcp-skill.stub'));
    }
});

it('creates a skill directory and a skill class', function (): void {
    try {
        $this->artisan('make:mcp-skill', [
            'name' => 'HouseStyle',
            '--class' => true,
            '--description' => "Use when: you write the team's copy.",
        ])->assertExitCode(0);

        $path = app_path('Mcp/Skills/HouseStyle.php');
        $contents = file_get_contents($path);

        expect($contents)->toContain('namespace App\Mcp\Skills;')
            ->and($contents)->toContain('class HouseStyle extends Skill')
            ->and($contents)->toContain("return resource_path('skills/house-style');");

        require_once $path;

        $skill = new HouseStyle;
        $skill->validate();

        expect($skill->description())->toBe("Use when: you write the team's copy.")
            ->and($skill->uri())->toBe('skill://house-style/SKILL.md')
            ->and($skill->eligibleForRegistration())->toBeTrue();

        $this->artisan('make:mcp-skill', ['name' => 'house-style', '--class' => true])
            ->expectsOutputToContain('already exists')
            ->assertExitCode(1);
    } finally {
        (new Filesystem)->deleteDirectory(app_path('Mcp'));
    }
});
