<?php

it('can create a resource class', function () {
    $response = $this->artisan('make:mcp-resource', [
        'name' => 'TestResource',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Resources/TestResource.php'));
});

it('may publish a custom stub', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'mcp-stubs',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(base_path('stubs/resource.stub'));
});
