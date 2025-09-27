<?php

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    // Clean up any existing test files
    cleanupTestFiles();
});

afterEach(function (): void {
    // Clean up test files after each test
    cleanupTestFiles();
});

it('creates all required MCP component files', function (): void {
    config(['app.name' => 'TestApp']);

    $this->artisan('mcp:quickstart')->assertExitCode(0);

    // Verify all expected files are created
    $this->assertFileExists(app_path('Mcp/Servers/TestApp.php'));
    $this->assertFileExists(app_path('Mcp/Tools/QuickstartTool.php'));
    $this->assertFileExists(app_path('Mcp/Resources/QuickstartResource.php'));
    $this->assertFileExists(app_path('Mcp/Prompts/QuickstartPrompt.php'));
});

it('publishes ai routes file', function (): void {
    $routesPath = base_path('routes/ai.php');

    // Ensure routes file doesn't exist initially
    if (File::exists($routesPath)) {
        File::delete($routesPath);
    }

    $this->artisan('mcp:quickstart')->assertExitCode(0);

    $this->assertFileExists($routesPath);
});

it('appends route to ai.php file', function (): void {
    config(['app.name' => 'MyTestApp']);

    $routesPath = base_path('routes/ai.php');

    File::ensureDirectoryExists(dirname($routesPath));
    File::put($routesPath, "<?php\n\n// Initial content\n");

    $this->artisan('mcp:quickstart')->assertExitCode(0);

    $routesContent = File::get($routesPath);
    $this->assertStringContainsString("Mcp::web('/mcp', App\\Mcp\\Servers\\MyTestApp::class)->name('mcp.quickstart');", $routesContent);
});

it('does not duplicate routes when run multiple times', function (): void {
    config(['app.name' => 'DuplicateTest']);

    $routesPath = base_path('routes/ai.php');

    File::ensureDirectoryExists(dirname($routesPath));
    File::put($routesPath, "<?php\n\n// Initial content\n");

    $this->artisan('mcp:quickstart')->assertExitCode(0);
    $this->artisan('mcp:quickstart')->assertExitCode(0);

    $routesContent = File::get($routesPath);
    $routeDeclaration = "Mcp::web('/mcp', App\\Mcp\\Servers\\DuplicateTest::class)->name('mcp.quickstart');";

    $this->assertEquals(1, substr_count($routesContent, $routeDeclaration));
});

it('sanitizes app name for server class', function (): void {
    config(['app.name' => 'My-Weird@App Name!']);

    $this->artisan('mcp:quickstart')->assertExitCode(0);

    $this->assertFileExists(app_path('Mcp/Servers/MyWeirdAppName.php'));

    $routesContent = File::get(base_path('routes/ai.php'));
    $this->assertStringContainsString('MyWeirdAppName::class', $routesContent);
});

it('uses default name when app name is not set', function (): void {
    config(['app.name' => '']);

    $this->artisan('mcp:quickstart')->assertExitCode(0);

    $this->assertFileExists(app_path('Mcp/Servers/Quickstart.php'));
});

it('uses quickstart-specific stubs for tool generation', function (): void {
    $this->artisan('mcp:quickstart')->assertExitCode(0);

    $toolContent = File::get(app_path('Mcp/Tools/QuickstartTool.php'));

    $this->assertStringContainsString('Add debug log entries', $toolContent);
    $this->assertStringContainsString('Log::debug($message)', $toolContent);
    $this->assertStringContainsString('Messages to add to the log as debug entries', $toolContent);

    $this->assertStringContainsString('messages', $toolContent);
    $this->assertStringContainsString('required|array', $toolContent);
    $this->assertStringContainsString('$schema->array()', $toolContent);
});

it('displays helpful output table', function (): void {
    $response = $this->artisan('mcp:quickstart');

    $response->expectsOutputToContain('routes/ai.php');
    $response->expectsOutputToContain('php artisan mcp:inspector mcp');
    $response->expectsOutputToContain('https://addmcp.fyi/Quickstart/');
});

it('shows security warning for https urls', function (): void {
    // Mock URL to return HTTPS
    app('url')->forceScheme('https');

    $response = $this->artisan('mcp:quickstart');

    $response->expectsOutputToContain('self-signed certificates');
});

it('creates directories when they do not exist', function (): void {
    // Ensure MCP directories don't exist
    File::deleteDirectory(app_path('Mcp'));

    $this->artisan('mcp:quickstart')->assertExitCode(0);

    $this->assertDirectoryExists(app_path('Mcp/Servers'));
    $this->assertDirectoryExists(app_path('Mcp/Tools'));
    $this->assertDirectoryExists(app_path('Mcp/Resources'));
    $this->assertDirectoryExists(app_path('Mcp/Prompts'));
});

// Helper method to clean up test files
function cleanupTestFiles(): void
{
    $filesToClean = [
        base_path('routes/ai.php'),
        app_path('Mcp/Servers'),
        app_path('Mcp/Tools'),
        app_path('Mcp/Resources'),
        app_path('Mcp/Prompts'),
        base_path('stubs'), // Clean up any published stubs
    ];

    foreach ($filesToClean as $path) {
        if (File::exists($path)) {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            } else {
                File::delete($path);
            }
        }
    }

}
