<?php

it('can create a resource class', function (): void {
    $response = $this->artisan('make:mcp-resource', [
        'name' => 'TestResource',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Resources/TestResource.php'));
});

it('can create a resource template class', function (): void {
    $response = $this->artisan('make:mcp-resource', [
        'name' => 'UserFileResource',
        '--template' => true,
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Resources/UserFileResource.php'));
});

it('may publish a custom stub', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'mcp-stubs',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(base_path('stubs/resource.stub'));
});

it('may publish a custom resource template stub', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'mcp-stubs',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(base_path('stubs/resource-template.stub'));
});
