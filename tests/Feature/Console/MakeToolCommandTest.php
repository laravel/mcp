<?php

it('can create a tool class', function () {
    $response = $this->artisan('make:mcp-tool', [
        'name' => 'TestTool',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Tools/TestTool.php'));
});
