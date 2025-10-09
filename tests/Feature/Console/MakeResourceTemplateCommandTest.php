<?php

it('can create a resource template class', function (): void {
    $response = $this->artisan('make:mcp-resource-template', [
        'name' => 'TestResourceTemplate',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/ResourceTemplates/TestResourceTemplate.php'));
});

it('may publish a custom stub', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'mcp-stubs',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(base_path('stubs/resource-template.stub'));
});
