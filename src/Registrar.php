<?php

namespace Laravel\Mcp;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Laravel\Mcp\Transport\Stdio;
use Laravel\Mcp\Transport\HttpTransport;
use Laravel\Mcp\Transport\StdioTransport;

class Registrar
{
    /**
     * The registered local servers running over STDIO.
     */
    private array $localServers = [];

    /**
     * Register an web-based MCP server running over HTTP.
     */
    public function web(string $handle, string $serverClass): Route
    {
        return Router::post($handle, fn () => $this->bootServer(
            $serverClass,
            fn () => new HttpTransport(request())
        ));
    }

    /**
     * Register a local MCP server running over STDIO.
     */
    public function local(string $handle, string $serverClass): void
    {
        $this->localServers[$handle] = fn () => $this->bootServer(
            $serverClass,
            fn () => new StdioTransport(new Stdio())
        );
    }

    /**
     * Get the server class for a local MCP.
     */
    public function getLocalServer(string $handle): ?callable
    {
        return $this->localServers[$handle] ?? null;
    }

    /**
     * Boot the MCP server.
     */
    private function bootServer(string $serverClass, callable $transportFactory)
    {
        $transport = $transportFactory();

        $server = new $serverClass();

        $server->connect($transport);

        return $transport->run();
    }
}
