<?php

namespace Laravel\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Transport\HttpSseTransport;
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

    public function sse($handle, string $serverClass)
    {
        $server = new $serverClass();

        Route::get($handle, function (Request $request) use ($server) {
            $transport = new HttpSseTransport($request);
            $server->connect($transport);

            return $transport->run();
        });

        Route::post($handle.'/messages', function (Request $request) use ($server) {
            $transport = new HttpSseTransport($request);
            $server->connect($transport);

            return $transport->run();
        });
    }

    public function local($handle, string $serverClass)
    {
        $server = new $serverClass();

        Artisan::command('mcp:' . $handle, function () use ($server) {
            $transport = new StdioTransport(STDIN, STDOUT);
            $server->connect($transport);

            $transport->run();
        });
    }
}
