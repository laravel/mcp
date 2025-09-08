<?php

it('can create a prompt class', function () {
    $response = $this->artisan('make:mcp-prompt', [
        'name' => 'TestPrompt',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Prompts/TestPrompt.php'));
});
