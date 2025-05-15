<?php

namespace Laravel\Mcp\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Mcp\Transport\HttpStreamTransport;

class Registrar
{
    public function web($handle, Server $server)
    {
        return Route::post($handle, function (Request $request) use ($server) {
            $transport = new HttpStreamTransport($request);
            $server->connect($transport);

            return $transport->run();
        })->prefix('mcp');
    }

    public function local($handle, Server $server)
    {
        Artisan::command('mcp:' . $handle, function () {
            //
        });
    }
}
