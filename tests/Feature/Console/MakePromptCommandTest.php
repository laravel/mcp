<?php

it('can create a prompt class', function (): void {
    $response = $this->artisan('make:mcp-prompt', [
        'name' => 'TestPrompt',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Prompts/TestPrompt.php'));
});

it('may publish a custom stub', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'mcp-stubs',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(base_path('stubs/prompt.stub'));
});
