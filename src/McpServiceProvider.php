<?php

namespace Laravel\Mcp;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Registrar;

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
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
