<?php

use Illuminate\Filesystem\Filesystem;
use Laravel\Mcp\Server\Skills\Frontmatter;

afterEach(function (): void {
    $filesystem = new Filesystem;

    foreach ([resource_path('skills'), app_path('Mcp')] as $directory) {
        if ($filesystem->isDirectory($directory)) {
            $filesystem->deleteDirectory($directory);
        }
    }

    foreach ([base_path('stubs/mcp-skill.stub'), base_path('stubs/mcp-skill.md.stub')] as $stub) {
        if ($filesystem->exists($stub)) {
            $filesystem->delete($stub);
        }
    }
});

it('creates a skill class and a valid SKILL.md file', function (): void {
    $this->artisan('make:mcp-skill', [
        'name' => 'ReleaseChecklist',
    ])->assertExitCode(0)->run();

    $class = file_get_contents(app_path('Mcp/Skills/ReleaseChecklist.php'));
    $frontmatter = Frontmatter::parse(file_get_contents(resource_path('skills/release-checklist/SKILL.md')));

    expect($class)->toContain('class ReleaseChecklist extends Skill')
        ->and($class)->toContain("return 'skills/release-checklist';")
        ->and($frontmatter['name'])->toBe('release-checklist')
        ->and(file_get_contents(resource_path('skills/release-checklist/SKILL.md')))->toContain('# Release Checklist');
});

it('preserves namespace segments in the skill directory', function (): void {
    $this->artisan('make:mcp-skill', [
        'name' => 'Billing/Refunds',
    ])->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Skills/Billing/Refunds.php'));
    $this->assertFileExists(resource_path('skills/billing/refunds/SKILL.md'));

    expect(file_get_contents(app_path('Mcp/Skills/Billing/Refunds.php')))->toContain("return 'skills/billing/refunds';")
        ->and(Frontmatter::parse(file_get_contents(resource_path('skills/billing/refunds/SKILL.md')))['name'])->toBe('refunds');
});

it('keeps an existing SKILL.md unless forced', function (): void {
    $this->artisan('make:mcp-skill', ['name' => 'ReleaseChecklist'])->assertExitCode(0)->run();
    file_put_contents(resource_path('skills/release-checklist/SKILL.md'), 'edited');

    $this->artisan('make:mcp-skill', ['name' => 'ReleaseChecklist'])->run();
    expect(file_get_contents(resource_path('skills/release-checklist/SKILL.md')))->toBe('edited');

    $this->artisan('make:mcp-skill', ['name' => 'ReleaseChecklist', '--force' => true])->assertExitCode(0)->run();
    expect(file_get_contents(resource_path('skills/release-checklist/SKILL.md')))->not->toBe('edited');
});

it('may publish custom stubs', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'mcp-stubs',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(base_path('stubs/mcp-skill.stub'));
    $this->assertFileExists(base_path('stubs/mcp-skill.md.stub'));
});
