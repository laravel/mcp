<?php

use Illuminate\Filesystem\Filesystem;

afterEach(function (): void {
    $filesystem = new Filesystem;

    $dir = resource_path('views/mcp');

    if ($filesystem->isDirectory($dir)) {
        $filesystem->deleteDirectory($dir);
    }
});

it('can create a ui resource class', function (): void {
    $response = $this->artisan('make:mcp-ui-resource', [
        'name' => 'TestUiResource',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Resources/TestUiResource.php'));
});

it('creates a blade view alongside the php class', function (): void {
    $this->artisan('make:mcp-ui-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Resources/DashboardResource.php'));
    $this->assertFileExists(resource_path('views/mcp/dashboard-resource.blade.php'));
});

it('does not generate a js entry file', function (): void {
    $this->artisan('make:mcp-ui-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $this->assertFileDoesNotExist(resource_path('js/mcp/dashboard-resource.js'));
});

it('generates php class that extends UiResource', function (): void {
    $this->artisan('make:mcp-ui-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $content = file_get_contents(app_path('Mcp/Resources/DashboardResource.php'));

    expect($content)->toContain('extends UiResource');
});

it('generates blade view with correct title', function (): void {
    $this->artisan('make:mcp-ui-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $content = file_get_contents(resource_path('views/mcp/dashboard-resource.blade.php'));

    expect($content)->toContain(':title="$title"');
});

it('generates blade view with mcp app component', function (): void {
    $this->artisan('make:mcp-ui-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $content = file_get_contents(resource_path('views/mcp/dashboard-resource.blade.php'));

    expect($content)->toContain('<x-mcp::app');
});

it('generates blade view with createMcpApp inline script', function (): void {
    $this->artisan('make:mcp-ui-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $content = file_get_contents(resource_path('views/mcp/dashboard-resource.blade.php'));

    expect($content)->toContain('createMcpApp')
        ->and($content)->not->toContain('entry=');
});

it('may publish custom stubs', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'mcp-stubs',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(base_path('stubs/mcp-ui-resource.stub'));
    $this->assertFileExists(base_path('stubs/mcp-ui-resource.view.stub'));
});

it('respects force flag for existing files', function (): void {
    $this->artisan('make:mcp-ui-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $this->artisan('make:mcp-ui-resource', [
        'name' => 'DashboardResource',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(resource_path('views/mcp/dashboard-resource.blade.php'));
});
