<?php

namespace Laravel\Mcp;

use Laravel\Mcp\Transport\Stdio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Session\ArraySessionStore;
use Laravel\Mcp\Session\DatabaseSessionStore;
use Laravel\Mcp\Transport\HttpTransport;
use Laravel\Mcp\Transport\StdioTransport;

class Registrar
{
    private array $cliServers = [];

    public function web(string $handle, string $serverClass)
    {
        return Route::post($handle, fn () => $this->bootServer(
            $serverClass,
            fn () => new DatabaseSessionStore(DB::connection()),
            fn () => new HttpTransport(request())
        ));
    }

    public function cli(string $handle, string $serverClass)
    {
        $this->cliServers[$handle] = fn () => $this->bootServer(
            $serverClass,
            fn () => new ArraySessionStore(),
            fn () => new StdioTransport(new Stdio())
        );
    }

    public function getCliServer(string $handle): ?callable
    {
        return $this->cliServers[$handle] ?? null;
    }

    private function bootServer(string $serverClass, callable $sessionStoreFactory, callable $transportFactory)
    {
        $sessionStore = $sessionStoreFactory();
        $transport = $transportFactory();

        $server = new $serverClass($sessionStore);

        $server->connect($transport);

        return $transport->run();
    }
}
