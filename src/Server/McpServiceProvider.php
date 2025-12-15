<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Console\Commands\InspectorCommand;
use Laravel\Mcp\Console\Commands\MakePromptCommand;
use Laravel\Mcp\Console\Commands\MakeResourceCommand;
use Laravel\Mcp\Console\Commands\MakeServerCommand;
use Laravel\Mcp\Console\Commands\MakeToolCommand;
use Laravel\Mcp\Console\Commands\StartCommand;
use Laravel\Mcp\Request;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registrar::class, fn (): Registrar => new Registrar);

        $this->mergeConfigFrom(__DIR__.'/../../config/mcp.php', 'mcp');
    }

    public function boot(): void
    {
        $this->registerMcpScope();
        $this->registerRoutes();
        $this->registerContainerCallbacks();

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->registerPublishing();
        }
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../../routes/ai.php' => base_path('routes/ai.php'),
        ], 'ai-routes');

        $this->publishes([
            __DIR__.'/../../resources/views/mcp/authorize.blade.php' => resource_path('views/mcp/authorize.blade.php'),
        ], 'mcp-views');

        $this->publishes([
            __DIR__.'/../../stubs/prompt.stub' => base_path('stubs/prompt.stub'),
            __DIR__.'/../../stubs/resource.stub' => base_path('stubs/resource.stub'),
            __DIR__.'/../../stubs/server.stub' => base_path('stubs/server.stub'),
            __DIR__.'/../../stubs/tool.stub' => base_path('stubs/tool.stub'),
        ], 'mcp-stubs');

        $this->publishes([
            __DIR__.'/../../config/mcp.php' => config_path('mcp.php'),
        ], 'mcp-config');
    }

    protected function registerRoutes(): void
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

    protected function registerContainerCallbacks(): void
    {
        $this->app->resolving(Request::class, function (Request $request, $app): void {
            if ($app->bound('mcp.request')) {
                /** @var Request $currentRequest */
                $currentRequest = $app->make('mcp.request');

                $request->setArguments($currentRequest->all());
                $request->setSessionId($currentRequest->sessionId());
                $request->setMeta($currentRequest->meta());
            }
        });
    }

    protected function registerCommands(): void
    {
        $this->commands([
            StartCommand::class,
            MakeServerCommand::class,
            MakeToolCommand::class,
            MakePromptCommand::class,
            MakeResourceCommand::class,
            InspectorCommand::class,
        ]);
    }

    protected function registerMcpScope(): void
    {
        $this->app->booted(function (): void {
            Registrar::ensureMcpScope();
        });
    }
}
