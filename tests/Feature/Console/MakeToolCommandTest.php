<?php

use Illuminate\Support\Facades\File;

it('can create a tool class', function (): void {
    $response = $this->artisan('make:mcp-tool', [
        'name' => 'TestTool',
    ]);

    $response->assertExitCode(0)->run();

    $this->assertFileExists(app_path('Mcp/Tools/TestTool.php'));
});

it('may publish a custom stub', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'mcp-stubs',
        '--force' => true,
    ])->assertExitCode(0)->run();

    $this->assertFileExists(base_path('stubs/tool.stub'));

    // Clean up published stubs
    if (File::exists(base_path('stubs'))) {
        File::deleteDirectory(base_path('stubs'));
    }
});
