<?php

namespace Laravel\Mcp;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Console\Commands\McpInspectorCommand;
use Laravel\Mcp\Registrar;
use Laravel\Mcp\Console\Commands\PruneSessionsCommand;
use Laravel\Mcp\Console\Commands\ServerMakeCommand;
use Laravel\Mcp\Console\Commands\ToolMakeCommand;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                McpInspectorCommand::class,
                PruneSessionsCommand::class,
                ServerMakeCommand::class,
                ToolMakeCommand::class,
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
        if ($this->app->runningInConsole()) {
            $this->offerPublishing();
        }

        $this->loadAiRoutes();
    }

    /**
     * Register the migrations and publishing for the package.
     *
     * @return void
     */
    protected function offerPublishing()
    {
        $this->publishes([
            __DIR__ . '/../stubs/routes/ai.php' => base_path('routes/ai.php'),
        ], 'ai-routes');

        $this->publishes([
            __DIR__.'/../config/mcp.php' => config_path('mcp.php'),
        ], 'mcp-config');

        $method = method_exists($this, 'publishesMigrations') ? 'publishesMigrations' : 'publishes';

        $this->{$method}([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'mcp-migrations');
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
