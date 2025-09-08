<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\ServerWithDynamicTools;

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
        $this->loadAiRoutes();
    }

    public function loadAiRoutes(): void
    {
        // Used in tests
        Mcp::local('test-mcp', ExampleServer::class);
        Mcp::web('test-mcp', ExampleServer::class);
        Mcp::web('test-mcp-dynamic-tools', ServerWithDynamicTools::class);
    }
}
