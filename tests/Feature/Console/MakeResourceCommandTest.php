<?php

use Illuminate\Support\Facades\File;

it('can create a resource class', function (): void {
    $response = $this->artisan('make:mcp-resource', [
        'name' => 'TestResource',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Resources/TestResource.php'));
});

it('may publish a custom stub', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'mcp-stubs',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(base_path('stubs/resource.stub'));

    // Clean up published stubs
    if (File::exists(base_path('stubs'))) {
        File::deleteDirectory(base_path('stubs'));
    }
});
