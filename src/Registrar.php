<?php

namespace Laravel\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Transport\Stdio;
use Laravel\Mcp\Transport\HttpTransport;
use Laravel\Mcp\Transport\StdioTransport;

class Registrar
{
    public function web($handle, string $serverClass)
    {
        $server = new $serverClass();

        return Route::post($handle, function (Request $request) use ($server) {
            $transport = new HttpTransport($request);
            $server->connect($transport);

            return $transport->run();
        });
    }

    public function cli($handle, string $serverClass)
    {
        $server = new $serverClass();

        Artisan::command('mcp:' . $handle, function () use ($server) {
            $transport = new StdioTransport(new Stdio());
            $server->connect($transport);

            $transport->run();
        })->setDescription('Start the ' . $handle . ' MCP server.');
    }
}
