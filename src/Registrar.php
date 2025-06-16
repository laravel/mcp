<?php

namespace Laravel\Mcp;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Laravel\Mcp\Transport\Stdio;
use Laravel\Mcp\Transport\HttpTransport;
use Laravel\Mcp\Transport\StdioTransport;

class Registrar
{
    private array $localServers = [];

    public function web(string $handle, string $serverClass): Route
    {
        return Router::post($handle, fn () => $this->bootServer(
            $serverClass,
            fn () => new HttpTransport(request())
        ));
    }

    public function local(string $handle, string $serverClass): void
    {
        $this->localServers[$handle] = fn () => $this->bootServer(
            $serverClass,
            fn () => new StdioTransport(new Stdio())
        );
    }

    public function getLocalServer(string $handle): ?callable
    {
        return $this->localServers[$handle] ?? null;
    }

    private function bootServer(string $serverClass, callable $transportFactory)
    {
        $transport = $transportFactory();

        $server = new $serverClass();

        $server->connect($transport);

        return $transport->run();
    }
}
