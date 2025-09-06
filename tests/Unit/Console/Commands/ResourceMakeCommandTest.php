<?php

use Illuminate\Filesystem\Filesystem;
use Laravel\Mcp\Console\Commands\ResourceMakeCommand;

beforeEach(function () {
    $this->filesystem = new Filesystem;
    $this->command = new ResourceMakeCommand($this->filesystem);

    // Clean up any existing test files
    $this->cleanupTestFiles();
});

afterEach(function () {
    $this->cleanupTestFiles();
});

it('creates a resource file with correct name and namespace', function () {
    $this->artisan('make:mcp-resource', ['name' => 'TestResource'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Resources/TestResource.php');
    expect(file_exists($expectedPath))->toBeTrue();

    $content = file_get_contents($expectedPath);
    expect($content)->toContain('namespace App\Mcp\Resources;');
    expect($content)->toContain('class TestResource extends Resource');
});

it('does not overwrite existing file without force option', function () {
    // Create initial file
    $this->artisan('make:mcp-resource', ['name' => 'ExistingResource'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Resources/ExistingResource.php');
    $originalContent = file_get_contents($expectedPath);

    // Modify the file
    file_put_contents($expectedPath, str_replace('ExistingResource', 'ModifiedResource', $originalContent));
    $modifiedContent = file_get_contents($expectedPath);

    // Try to create again without force
    $this->artisan('make:mcp-resource', ['name' => 'ExistingResource'])
        ->assertExitCode(0);

    // Content should remain modified (not overwritten)
    expect(file_get_contents($expectedPath))->toBe($modifiedContent);
});

it('overwrites existing file with force option', function () {
    // Create initial file
    $this->artisan('make:mcp-resource', ['name' => 'ForceResource'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Resources/ForceResource.php');
    $originalContent = file_get_contents($expectedPath);

    // Modify the file
    file_put_contents($expectedPath, 'modified content');

    // Create again with force
    $this->artisan('make:mcp-resource', ['name' => 'ForceResource', '--force' => true])
        ->assertExitCode(0);

    // Content should be restored to original
    $newContent = file_get_contents($expectedPath);
    expect($newContent)->toContain('class ForceResource extends Resource');
    expect($newContent)->not->toBe('modified content');
});

it('creates resource file with correct stub content structure', function () {
    $this->artisan('make:mcp-resource', ['name' => 'StubTestResource'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Resources/StubTestResource.php');
    $content = file_get_contents($expectedPath);

    // Check for key stub elements
    expect($content)->toContain('use Laravel\Mcp\Server\Resource;');
    expect($content)->toContain('protected string $description = \'A description of what this resource contains.\';');
    expect($content)->toContain('public function read(): string');
    expect($content)->toContain('return \'Implement resource retrieval logic here\';');
});

it('creates resource with correct method signature', function () {
    $this->artisan('make:mcp-resource', ['name' => 'SignatureResource'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Resources/SignatureResource.php');
    $content = file_get_contents($expectedPath);

    // Check method signature
    expect($content)->toContain('public function read(): string');
});

it('creates resource with description property', function () {
    $this->artisan('make:mcp-resource', ['name' => 'DescriptionResource'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Resources/DescriptionResource.php');
    $content = file_get_contents($expectedPath);

    // Check for description property
    expect($content)->toContain('protected string $description = \'A description of what this resource contains.\';');
});

it('creates resource with placeholder implementation', function () {
    $this->artisan('make:mcp-resource', ['name' => 'PlaceholderResource'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Resources/PlaceholderResource.php');
    $content = file_get_contents($expectedPath);

    // Check for placeholder implementation
    expect($content)->toContain('return \'Implement resource retrieval logic here\';');
});

function cleanupTestFiles(): void
{
    $testFiles = [
        app_path('Mcp/Resources/TestResource.php'),
        app_path('Mcp/Resources/ExistingResource.php'),
        app_path('Mcp/Resources/ForceResource.php'),
        app_path('Mcp/Resources/StubTestResource.php'),
        app_path('Mcp/Resources/SignatureResource.php'),
        app_path('Mcp/Resources/DescriptionResource.php'),
        app_path('Mcp/Resources/PlaceholderResource.php'),
    ];

    foreach ($testFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Clean up directories if empty
    $dirs = [
        app_path('Mcp/Resources'),
        app_path('Mcp'),
    ];

    foreach ($dirs as $dir) {
        if (is_dir($dir) && count(scandir($dir)) === 2) { // Only . and ..
            rmdir($dir);
        }
    }
}
