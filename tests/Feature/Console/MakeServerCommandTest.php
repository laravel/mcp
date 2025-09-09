<?php

it('can create a server class', function (): void {
    $response = $this->artisan('make:mcp-server', [
        'name' => 'TestServer',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Servers/TestServer.php'));
});

it('may publish a custom stub', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'mcp-stubs',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(base_path('stubs/server.stub'));
});
