<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Console\Commands\McpInspectorCommand;
use Laravel\Mcp\Console\Commands\ResourceMakeCommand;
use Laravel\Mcp\Console\Commands\ServerMakeCommand;
use Laravel\Mcp\Console\Commands\StartServerCommand;
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
        $this->app->singleton('mcp', fn () => new Registrar);

        if ($this->app->runningInConsole()) {
            $this->commands([
                StartServerCommand::class,
                ServerMakeCommand::class,
                ToolMakeCommand::class,
                ResourceMakeCommand::class,
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
        if ($this->app->runningInConsole()) {
            $this->offerPublishing();
        }

        $this->loadAiRoutes();
    }

    /**
     * Register the migrations and publishing for the package.
     */
    protected function offerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../../routes/ai.php' => base_path('routes/ai.php'),
        ], 'ai-routes');
    }

    /**
     * Load the AI routes file if it exists.
     */
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
