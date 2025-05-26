<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Tests\Fixtures\ExampleServer;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Used in tests
        Mcp::cli('test-mcp', ExampleServer::class);
        Mcp::web('test-mcp', ExampleServer::class);
    }
}
