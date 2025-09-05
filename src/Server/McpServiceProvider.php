<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Console\Commands\McpInspectorCommand;
use Laravel\Mcp\Console\Commands\PromptMakeCommand;
use Laravel\Mcp\Console\Commands\ResourceMakeCommand;
use Laravel\Mcp\Console\Commands\ServerMakeCommand;
use Laravel\Mcp\Console\Commands\StartServerCommand;
use Laravel\Mcp\Console\Commands\ToolMakeCommand;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('mcp', fn () => new Registrar);

        if ($this->app->runningInConsole()) {
            $this->commands([
                StartServerCommand::class,
                ServerMakeCommand::class,
                ToolMakeCommand::class,
                PromptMakeCommand::class,
                ResourceMakeCommand::class,
                McpInspectorCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->offerPublishing();
        }

        $this->loadAiRoutes();
    }

    protected function offerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../../routes/ai.php' => base_path('routes/ai.php'),
        ], 'ai-routes');
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

        Route::group([], $path);
    }
}
