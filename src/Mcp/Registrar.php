<?php

namespace Laravel\Mcp\Mcp;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

class Registrar
{
    public function web()
    {
        return Route::get('/mcp', function () {
            return 'Starting MCP server...';
        });
    }

    public function local()
    {
        Artisan::command('mcp', function () {
            /** @var \Illuminate\Foundation\Console\ClosureCommand $this */
            $this->info('Starting MCP server...');
        });
    }
}
