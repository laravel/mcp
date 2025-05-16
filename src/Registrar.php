<?php

namespace Laravel\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Transport\HttpStreamTransport;
use Laravel\Mcp\Transport\StdioTransport;

class Registrar
{
    private function handler(string $serverClass, callable $transportFactory)
    {
        return function (...$arguments) use ($serverClass, $transportFactory) {
            $server = new $serverClass();
            $transport = $transportFactory(...$arguments);

            $server->connect($transport);

            return $transport->run();
        };
    }

    public function web($handle, string $serverClass)
    {
        return Route::post($handle, $this->handler($serverClass, function (Request $request) {
            return new HttpStreamTransport($request);
        }))->prefix('mcp');
    }

    public function local($handle, string $serverClass)
    {
        return Artisan::command('mcp:' . $handle, $this->handler($serverClass, function () {
            return new StdioTransport(STDIN, STDOUT);
        }));
    }
}
