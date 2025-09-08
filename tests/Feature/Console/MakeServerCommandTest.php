<?php

it('can create a server class', function () {
    $response = $this->artisan('make:mcp-server', [
        'name' => 'TestServer',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Servers/TestServer.php'));
});
