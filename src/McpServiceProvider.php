<?php

namespace Laravel\Mcp;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Commands\McpInspectorCommand;
use Laravel\Mcp\Registrar;
use Laravel\Mcp\Contracts\Stdio as StdioContract;
use Laravel\Mcp\Support\Stdio;

class McpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('mcp', function ($app) {
            return new Registrar();
        });

        $this->app->bind(StdioContract::class, Stdio::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                McpInspectorCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../stubs/routes/ai.php' => base_path('routes/ai.php'),
        ], 'ai-routes');

        $this->loadAiRoutes();
    }

    protected function loadAiRoutes(): void
    {
        $path = base_path('routes/ai.php');

        if (! file_exists($path)) {
            return;
        }

        if (! $this->app->runningInConsole() && $this->app->routesAreCached()) {
            return;
        }

        Route::prefix('mcp')->group($path);
    }
}
