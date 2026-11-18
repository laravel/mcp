<?php

use Illuminate\Filesystem\Filesystem;

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

it('creates a skill class and a SKILL.md file', function (string $name, string $class, string $directory): void {
    $this->artisan('make:mcp-skill', ['name' => $name])->assertExitCode(0)->run();

    expect(file_get_contents(app_path($class)))->toContain("return '{$directory}';")
        ->and(file_get_contents(resource_path("{$directory}/SKILL.md")))->toStartWith("---\nname: ".basename($directory)."\n");
})->with([
    ['ReleaseChecklist', 'Mcp/Skills/ReleaseChecklist.php', 'skills/release-checklist'],
    ['Billing/Refunds', 'Mcp/Skills/Billing/Refunds.php', 'skills/refunds'],
    ['Release_Checklist', 'Mcp/Skills/Release_Checklist.php', 'skills/release-checklist'],
]);

it('keeps an existing SKILL.md unless forced', function (): void {
    $this->artisan('make:mcp-skill', ['name' => 'ReleaseChecklist'])->assertExitCode(0)->run();
    file_put_contents(resource_path('skills/release-checklist/SKILL.md'), 'edited');

    unlink(app_path('Mcp/Skills/ReleaseChecklist.php'));

    $this->artisan('make:mcp-skill', ['name' => 'ReleaseChecklist'])
        ->expectsOutputToContain('already exists')
        ->assertExitCode(0)
        ->run();

    expect(file_get_contents(resource_path('skills/release-checklist/SKILL.md')))->toBe('edited')
        ->and(app_path('Mcp/Skills/ReleaseChecklist.php'))->toBeFile();

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

it('fails when the skill name is longer than 64 characters', function (): void {
    $this->artisan('make:mcp-skill', ['name' => str_repeat('a', 65)])
        ->expectsOutputToContain('must not be longer than 64 characters')
        ->assertFailed()
        ->run();

    expect(app_path('Mcp/Skills/'.str_repeat('a', 65).'.php'))->not->toBeFile();
});
