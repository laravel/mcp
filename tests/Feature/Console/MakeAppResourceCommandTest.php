<?php

use Illuminate\Filesystem\Filesystem;

afterEach(function (): void {
    $filesystem = new Filesystem;

    $dir = resource_path('views/mcp');

    if ($filesystem->isDirectory($dir)) {
        $filesystem->deleteDirectory($dir);
    }

    $mcpDir = app_path('Mcp');

    if ($filesystem->isDirectory($mcpDir)) {
        $filesystem->deleteDirectory($mcpDir);
    }

    foreach ([base_path('stubs/mcp-app-resource.stub'), base_path('stubs/mcp-app-resource.view.stub')] as $stub) {
        if ($filesystem->exists($stub)) {
            $filesystem->delete($stub);
        }
    }
});

it('can create an app resource class', function (): void {
    $response = $this->artisan('make:mcp-app-resource', [
        'name' => 'TestAppResource',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Resources/TestAppResource.php'));
});

it('creates a blade view alongside the php class', function (): void {
    $this->artisan('make:mcp-app-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Resources/DashboardResource.php'));
    $this->assertFileExists(resource_path('views/mcp/dashboard-resource.blade.php'));
});

it('does not generate a js entry file', function (): void {
    $this->artisan('make:mcp-app-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $this->assertFileDoesNotExist(resource_path('js/mcp/dashboard-resource.js'));
});

it('generates php class that extends app resource', function (): void {
    $this->artisan('make:mcp-app-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $content = file_get_contents(app_path('Mcp/Resources/DashboardResource.php'));

    expect($content)->toContain('extends AppResource');
});

it('generates blade view with mcp app component', function (): void {
    $this->artisan('make:mcp-app-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $content = file_get_contents(resource_path('views/mcp/dashboard-resource.blade.php'));

    expect($content)->toContain('<x-mcp::app');
});

it('generates blade view with createMcpApp inline script', function (): void {
    $this->artisan('make:mcp-app-resource', [
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

    $this->assertFileExists(base_path('stubs/mcp-app-resource.stub'));
    $this->assertFileExists(base_path('stubs/mcp-app-resource.view.stub'));
});

it('preserves namespace segments in view paths', function (): void {
    $this->artisan('make:mcp-app-resource', [
        'name' => 'Admin/DashboardApp',
    ])->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Resources/Admin/DashboardApp.php'));
    $this->assertFileExists(resource_path('views/mcp/admin/dashboard-app.blade.php'));

    $content = file_get_contents(app_path('Mcp/Resources/Admin/DashboardApp.php'));

    expect($content)->toContain("Response::view('mcp.admin.dashboard-app'");
});

it('generates unique views for different namespaces with same class name', function (): void {
    $this->artisan('make:mcp-app-resource', [
        'name' => 'Admin/DashboardApp',
    ])->assertExitCode(0)->run();

    $this->artisan('make:mcp-app-resource', [
        'name' => 'Reports/DashboardApp',
    ])->assertExitCode(0)->run();

    $this->assertFileExists(resource_path('views/mcp/admin/dashboard-app.blade.php'));
    $this->assertFileExists(resource_path('views/mcp/reports/dashboard-app.blade.php'));
});

it('generates stub without unused imports', function (): void {
    $this->artisan('make:mcp-app-resource', [
        'name' => 'CleanResource',
    ])->assertExitCode(0)->run();

    $content = file_get_contents(app_path('Mcp/Resources/CleanResource.php'));

    expect($content)->not->toContain('use Laravel\Mcp\Server\Ui\Enums\Permission');
});

it('forwards title to the app component in generated view', function (): void {
    $this->artisan('make:mcp-app-resource', [
        'name' => 'TitledResource',
    ])->assertExitCode(0)->run();

    $content = file_get_contents(resource_path('views/mcp/titled-resource.blade.php'));

    expect($content)->toContain('<x-mcp::app :title="$title">');
});

it('respects force flag for existing files', function (): void {
    $this->artisan('make:mcp-app-resource', [
        'name' => 'DashboardResource',
    ])->assertExitCode(0)->run();

    $this->artisan('make:mcp-app-resource', [
        'name' => 'DashboardResource',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(resource_path('views/mcp/dashboard-resource.blade.php'));
});
