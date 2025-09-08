<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Console\Commands\McpInspectorCommand;
use Laravel\Mcp\Console\Commands\PromptMakeCommand;
use Laravel\Mcp\Console\Commands\ResourceMakeCommand;
use Laravel\Mcp\Console\Commands\ServerMakeCommand;
use Laravel\Mcp\Console\Commands\StartServerCommand;
use Laravel\Mcp\Console\Commands\ToolMakeCommand;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

class McpServiceProvider extends ServiceProvider
{
    protected static $authRoutesLoaded = false;

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

        $this->addRouteAuthMacro();
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
        // Maybe here we can detect if auth is used, then loop through and add everything, so maybe this is better actually
        $this->loadRoutesFrom(__DIR__.'/../../routes/auth.php');
    }

    private function addRouteAuthMacro()
    {
        \Illuminate\Routing\Route::macro('withAuth', function () {
            // TODO: Only load the auth routes once
            // And only if the user actually uses 'withAuth'

            $this->middleware(AddWwwAuthenticateHeader::class);

            // OAuth Resource Server
            $path = sprintf('/%s/.well-known/oauth-protected-resource', $this->uri);
            Router::get($path, function (Request $request) {
                return response()->json([
                    'resource' => url($this->uri),
                    'authorization_servers' => [route('mcp.oauth.authorization-server')],
                ]);
            })->name('mcp.oauth.protected-resource');
        });
    }
}
