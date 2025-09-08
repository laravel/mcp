<?php

it('can create a resource class', function () {
    $response = $this->artisan('make:mcp-resource', [
        'name' => 'TestResource',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Resources/TestResource.php'));
});
