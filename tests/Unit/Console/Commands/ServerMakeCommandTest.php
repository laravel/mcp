<?php

use Illuminate\Filesystem\Filesystem;
use Laravel\Mcp\Console\Commands\ServerMakeCommand;

beforeEach(function () {
    $this->filesystem = new Filesystem;
    $this->command = new ServerMakeCommand($this->filesystem);

    // Clean up any existing test files
    $this->cleanupTestFiles();
});

afterEach(function () {
    $this->cleanupTestFiles();
});

it('creates a server file with correct name and namespace', function () {
    $this->artisan('make:mcp-server', ['name' => 'TestServer'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Servers/TestServer.php');
    expect(file_exists($expectedPath))->toBeTrue();

    $content = file_get_contents($expectedPath);
    expect($content)->toContain('namespace App\Mcp\Servers;');
    expect($content)->toContain('class TestServer extends Server');
    expect($content)->toContain('public string $name = \'Test Server\';');
});

it('creates server file with correct display name transformation', function () {
    $this->artisan('make:mcp-server', ['name' => 'MyAwesomeServer'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Servers/MyAwesomeServer.php');
    $content = file_get_contents($expectedPath);

    expect($content)->toContain('public string $name = \'My Awesome Server\';');
});

it('does not overwrite existing file without force option', function () {
    // Create initial file
    $this->artisan('make:mcp-server', ['name' => 'ExistingServer'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Servers/ExistingServer.php');
    $originalContent = file_get_contents($expectedPath);

    // Modify the file
    file_put_contents($expectedPath, str_replace('ExistingServer', 'ModifiedServer', $originalContent));
    $modifiedContent = file_get_contents($expectedPath);

    // Try to create again without force
    $this->artisan('make:mcp-server', ['name' => 'ExistingServer'])
        ->assertExitCode(0);

    // Content should remain modified (not overwritten)
    expect(file_get_contents($expectedPath))->toBe($modifiedContent);
});

it('overwrites existing file with force option', function () {
    // Create initial file
    $this->artisan('make:mcp-server', ['name' => 'ForceServer'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Servers/ForceServer.php');
    $originalContent = file_get_contents($expectedPath);

    // Modify the file
    file_put_contents($expectedPath, 'modified content');

    // Create again with force
    $this->artisan('make:mcp-server', ['name' => 'ForceServer', '--force' => true])
        ->assertExitCode(0);

    // Content should be restored to original
    $newContent = file_get_contents($expectedPath);
    expect($newContent)->toContain('class ForceServer extends Server');
    expect($newContent)->not->toBe('modified content');
});

it('creates server file with correct stub content structure', function () {
    $this->artisan('make:mcp-server', ['name' => 'StubTestServer'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Servers/StubTestServer.php');
    $content = file_get_contents($expectedPath);

    // Check for key stub elements
    expect($content)->toContain('use Laravel\Mcp\Server;');
    expect($content)->toContain('public string $version = \'0.0.1\';');
    expect($content)->toContain('public array $tools = [');
    expect($content)->toContain('public array $resources = [');
    expect($content)->toContain('public array $prompts = [');
    expect($content)->toContain('Instructions describing how to use the server');
});

function cleanupTestFiles(): void
{
    $testFiles = [
        app_path('Mcp/Servers/TestServer.php'),
        app_path('Mcp/Servers/MyAwesomeServer.php'),
        app_path('Mcp/Servers/ExistingServer.php'),
        app_path('Mcp/Servers/ForceServer.php'),
        app_path('Mcp/Servers/StubTestServer.php'),
    ];

    foreach ($testFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Clean up directories if empty
    $dirs = [
        app_path('Mcp/Servers'),
        app_path('Mcp'),
    ];

    foreach ($dirs as $dir) {
        if (is_dir($dir) && count(scandir($dir)) === 2) { // Only . and ..
            rmdir($dir);
        }
    }
}
