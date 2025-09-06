<?php

use Illuminate\Filesystem\Filesystem;
use Laravel\Mcp\Console\Commands\ToolMakeCommand;

beforeEach(function () {
    $this->filesystem = new Filesystem;
    $this->command = new ToolMakeCommand($this->filesystem);

    // Clean up any existing test files
    $this->cleanupTestFiles();
});

afterEach(function () {
    $this->cleanupTestFiles();
});

it('creates a tool file with correct name and namespace', function () {
    $this->artisan('make:mcp-tool', ['name' => 'TestTool'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Tools/TestTool.php');
    expect(file_exists($expectedPath))->toBeTrue();

    $content = file_get_contents($expectedPath);
    expect($content)->toContain('namespace App\Mcp\Tools;');
    expect($content)->toContain('class TestTool extends Tool');
});

it('creates tool file with correct title transformation', function () {
    $this->artisan('make:mcp-tool', ['name' => 'MyAwesomeTool'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Tools/MyAwesomeTool.php');
    $content = file_get_contents($expectedPath);

    expect($content)->toContain('#[Title(\'My Awesome Tool\')]');
});

it('does not overwrite existing file without force option', function () {
    // Create initial file
    $this->artisan('make:mcp-tool', ['name' => 'ExistingTool'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Tools/ExistingTool.php');
    $originalContent = file_get_contents($expectedPath);

    // Modify the file
    file_put_contents($expectedPath, str_replace('ExistingTool', 'ModifiedTool', $originalContent));
    $modifiedContent = file_get_contents($expectedPath);

    // Try to create again without force
    $this->artisan('make:mcp-tool', ['name' => 'ExistingTool'])
        ->assertExitCode(0);

    // Content should remain modified (not overwritten)
    expect(file_get_contents($expectedPath))->toBe($modifiedContent);
});

it('overwrites existing file with force option', function () {
    // Create initial file
    $this->artisan('make:mcp-tool', ['name' => 'ForceTool'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Tools/ForceTool.php');
    $originalContent = file_get_contents($expectedPath);

    // Modify the file
    file_put_contents($expectedPath, 'modified content');

    // Create again with force
    $this->artisan('make:mcp-tool', ['name' => 'ForceTool', '--force' => true])
        ->assertExitCode(0);

    // Content should be restored to original
    $newContent = file_get_contents($expectedPath);
    expect($newContent)->toContain('class ForceTool extends Tool');
    expect($newContent)->not->toBe('modified content');
});

it('creates tool file with correct stub content structure', function () {
    $this->artisan('make:mcp-tool', ['name' => 'StubTestTool'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Tools/StubTestTool.php');
    $content = file_get_contents($expectedPath);

    // Check for key stub elements
    expect($content)->toContain('use Laravel\Mcp\Request;');
    expect($content)->toContain('use Laravel\Mcp\Server\Tool;');
    expect($content)->toContain('use Laravel\Mcp\Server\Tools\ToolResult;');
    expect($content)->toContain('use Laravel\Mcp\Server\Tools\Annotations\Title;');
    expect($content)->toContain('use Illuminate\JsonSchema\JsonSchema;');
    expect($content)->toContain('public function handle(Request $request): ToolResult');
    expect($content)->toContain('public function schema(JsonSchema $schema): array');
    expect($content)->toContain('protected string $description = \'A description of what this tool does.\';');
});

it('creates tool with correct method signatures', function () {
    $this->artisan('make:mcp-tool', ['name' => 'SignatureTool'])
        ->assertExitCode(0);

    $expectedPath = app_path('Mcp/Tools/SignatureTool.php');
    $content = file_get_contents($expectedPath);

    // Check method signatures
    expect($content)->toContain('public function handle(Request $request): ToolResult');
    expect($content)->toContain('public function schema(JsonSchema $schema): array');
    expect($content)->toContain('return ToolResult::text(\'Tool executed successfully.\');');
});

function cleanupTestFiles(): void
{
    $testFiles = [
        app_path('Mcp/Tools/TestTool.php'),
        app_path('Mcp/Tools/MyAwesomeTool.php'),
        app_path('Mcp/Tools/ExistingTool.php'),
        app_path('Mcp/Tools/ForceTool.php'),
        app_path('Mcp/Tools/StubTestTool.php'),
        app_path('Mcp/Tools/SignatureTool.php'),
    ];

    foreach ($testFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Clean up directories if empty
    $dirs = [
        app_path('Mcp/Tools'),
        app_path('Mcp'),
    ];

    foreach ($dirs as $dir) {
        if (is_dir($dir) && count(scandir($dir)) === 2) { // Only . and ..
            rmdir($dir);
        }
    }
}
