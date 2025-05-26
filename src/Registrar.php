<?php

namespace Laravel\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Support\Stdio;
use Laravel\Mcp\Transport\HttpStreamTransport;
use Laravel\Mcp\Transport\StdioTransport;

class Registrar
{
    public function web($handle, string $serverClass)
    {
        $server = new $serverClass();

        return Route::post($handle, function (Request $request) use ($server) {
            $transport = new HttpStreamTransport($request);
            $server->connect($transport);

            return $transport->run();
        });
    }

    public function cli($handle, string $serverClass)
    {
        $server = new $serverClass();
        $stdio = app(Stdio::class);

        Artisan::command('mcp:' . $handle, function () use ($server, $stdio) {
            $transport = new StdioTransport($stdio);
            $server->connect($transport);

            $transport->run();
        })->setDescription('MCP server command for ' . $handle);
    }
}
