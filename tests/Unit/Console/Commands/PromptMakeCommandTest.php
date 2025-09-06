<?php

use Illuminate\Filesystem\Filesystem;
use Laravel\Mcp\Console\Commands\PromptMakeCommand;

beforeEach(function () {
    $this->filesystem = new Filesystem;
    $this->command = new PromptMakeCommand($this->filesystem);

    // Clean up any existing test files
    $this->cleanupTestFiles();
});

afterEach(function () {
    $this->cleanupTestFiles();
});

it('creates a prompt file with correct name and namespace', function () {
    $this->artisan('make:mcp-prompt', ['name' => 'TestPrompt'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Prompts/TestPrompt.php');
    expect(file_exists($expectedPath))->toBeTrue();

    $content = file_get_contents($expectedPath);
    expect($content)->toContain('namespace App\Mcp\Prompts;');
    expect($content)->toContain('class TestPrompt extends Prompt');
});

it('does not overwrite existing file without force option', function () {
    // Create initial file
    $this->artisan('make:mcp-prompt', ['name' => 'ExistingPrompt'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Prompts/ExistingPrompt.php');
    $originalContent = file_get_contents($expectedPath);

    // Modify the file
    file_put_contents($expectedPath, str_replace('ExistingPrompt', 'ModifiedPrompt', $originalContent));
    $modifiedContent = file_get_contents($expectedPath);

    // Try to create again without force
    $this->artisan('make:mcp-prompt', ['name' => 'ExistingPrompt'])
        ->assertExitCode(0);

    // Content should remain modified (not overwritten)
    expect(file_get_contents($expectedPath))->toBe($modifiedContent);
});

it('overwrites existing file with force option', function () {
    // Create initial file
    $this->artisan('make:mcp-prompt', ['name' => 'ForcePrompt'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Prompts/ForcePrompt.php');
    $originalContent = file_get_contents($expectedPath);

    // Modify the file
    file_put_contents($expectedPath, 'modified content');

    // Create again with force
    $this->artisan('make:mcp-prompt', ['name' => 'ForcePrompt', '--force' => true])
        ->assertExitCode(0);

    // Content should be restored to original
    $newContent = file_get_contents($expectedPath);
    expect($newContent)->toContain('class ForcePrompt extends Prompt');
    expect($newContent)->not->toBe('modified content');
});

it('creates prompt file with correct stub content structure', function () {
    $this->artisan('make:mcp-prompt', ['name' => 'StubTestPrompt'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Prompts/StubTestPrompt.php');
    $content = file_get_contents($expectedPath);

    // Check for key stub elements
    expect($content)->toContain('use Laravel\Mcp\Request;');
    expect($content)->toContain('use Laravel\Mcp\Server\Prompt;');
    expect($content)->toContain('use Laravel\Mcp\Server\Prompts\Argument;');
    expect($content)->toContain('use Laravel\Mcp\Server\Prompts\Arguments;');
    expect($content)->toContain('use Laravel\Mcp\Server\Prompts\PromptResult;');
    expect($content)->toContain('protected string $description = \'Instructions for how to review my code\';');
});

it('creates prompt with correct method signatures', function () {
    $this->artisan('make:mcp-prompt', ['name' => 'SignaturePrompt'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Prompts/SignaturePrompt.php');
    $content = file_get_contents($expectedPath);

    // Check method signatures
    expect($content)->toContain('public function arguments(): Arguments');
    expect($content)->toContain('public function handle(Request $request): PromptResult');
    expect($content)->toContain('return new PromptResult(');
});

it('creates prompt with example argument structure', function () {
    $this->artisan('make:mcp-prompt', ['name' => 'ArgumentPrompt'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Prompts/ArgumentPrompt.php');
    $content = file_get_contents($expectedPath);

    // Check for argument example
    expect($content)->toContain('new Argument(');
    expect($content)->toContain('name: \'language\',');
    expect($content)->toContain('description: \'The language the code is in\',');
    expect($content)->toContain('required: true,');
});

it('creates prompt with match statement example', function () {
    $this->artisan('make:mcp-prompt', ['name' => 'MatchPrompt'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Prompts/MatchPrompt.php');
    $content = file_get_contents($expectedPath);

    // Check for match statement
    expect($content)->toContain('$instructions = match ($request->get(\'language\')) {');
    expect($content)->toContain('\'php\' => \'Review the code carefully\',');
    expect($content)->toContain('null => \'Don\\\'t worry about it\',');
});

function cleanupTestFiles(): void
{
    $testFiles = [
        app_path('Mcp/Prompts/TestPrompt.php'),
        app_path('Mcp/Prompts/ExistingPrompt.php'),
        app_path('Mcp/Prompts/ForcePrompt.php'),
        app_path('Mcp/Prompts/StubTestPrompt.php'),
        app_path('Mcp/Prompts/SignaturePrompt.php'),
        app_path('Mcp/Prompts/ArgumentPrompt.php'),
        app_path('Mcp/Prompts/MatchPrompt.php'),
    ];

    foreach ($testFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Clean up directories if empty
    $dirs = [
        app_path('Mcp/Prompts'),
        app_path('Mcp'),
    ];

    foreach ($dirs as $dir) {
        if (is_dir($dir) && count(scandir($dir)) === 2) { // Only . and ..
            rmdir($dir);
        }
    }
}
