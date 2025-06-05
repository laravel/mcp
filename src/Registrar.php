<?php

namespace Laravel\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Transport\HttpTransport;
use Laravel\Mcp\Session\DatabaseSessionStore;

class Registrar
{
    private array $servers = [];

    public function web($handle, string $serverClass)
    {
        return Route::post($handle, function (Request $request) use ($serverClass) {
            $sessionStore = new DatabaseSessionStore(DB::connection());
            $server = new $serverClass($sessionStore);

            $transport = new HttpTransport($request);
            $server->connect($transport);

            return $transport->run();
        });
    }

    public function cli($handle, string $serverClass)
    {
        $this->servers[$handle] = $serverClass;
    }

    public function getServer(string $handle): ?string
    {
        return $this->servers[$handle] ?? null;
    }
}
