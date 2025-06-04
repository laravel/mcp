<?php

namespace Laravel\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Transport\Stdio;
use Laravel\Mcp\Transport\HttpTransport;
use Laravel\Mcp\Transport\StdioTransport;
use Laravel\Mcp\Session\ArraySessionStore;
use Laravel\Mcp\Session\DatabaseSessionStore;

class Registrar
{
    public function web($handle, string $serverClass)
    {
        $sessionStore = new DatabaseSessionStore(DB::connection());
        $server = new $serverClass($sessionStore);

        return Route::post($handle, function (Request $request) use ($server) {
            $transport = new HttpTransport($request);
            $server->connect($transport);

            return $transport->run();
        });
    }

    public function cli($handle, string $serverClass)
    {
        $sessionStore = new ArraySessionStore();
        $server = new $serverClass($sessionStore);

        Artisan::command('mcp:' . $handle, function () use ($server) {
            $transport = new StdioTransport(new Stdio());
            $server->connect($transport);

            $transport->run();
        })->setDescription('Start the ' . $handle . ' MCP server.');
    }
}
