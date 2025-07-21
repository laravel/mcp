<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Laravel\Mcp\Server\Transport\HttpTransport;
use Laravel\Mcp\Server\Transport\StdioTransport;

class Registrar
{
    /**
     * The registered local servers running over STDIO.
     */
    private array $localServers = [];

    protected array $registeredWebServers = [];

    /**
     * Register an web-based MCP server running over HTTP.
     */
    public function web(string $handle, string $serverClass): Route
    {
        $this->registeredWebServers[$handle] = $serverClass;

        return Router::post($handle, fn () => $this->bootServer(
            $serverClass,
            fn () => new HttpTransport(request())
        ))->name('mcp-server.'.$handle);
    }

    /**
     * Register a local MCP server running over STDIO.
     */
    public function local(string $handle, string $serverClass): void
    {
        $this->localServers[$handle] = fn () => $this->bootServer(
            $serverClass,
            fn () => new StdioTransport
        );
    }

    /**
     * Get the server class for a local MCP.
     */
    public function getLocalServer(string $handle): ?callable
    {
        return $this->localServers[$handle] ?? null;
    }

    public function getWebServer(string $handle): ?string
    {
        return $this->registeredWebServers[$handle] ?? null;
    }

    public function oauthRoutes($oauthPrefix = 'oauth')
    {
        Router::get('/.well-known/oauth-protected-resource', function () {
            return response()->json([
                'resource' => config('app.url'),
                'authorization_server' => url('/.well-known/oauth-authorization-server'),
            ]);
        });

        Router::get('/.well-known/oauth-authorization-server', function () use ($oauthPrefix) {
            return response()->json([
                'issuer' => config('app.url'),
                'authorization_endpoint' => url($oauthPrefix.'/authorize'),
                'token_endpoint' => url($oauthPrefix.'/token'),
                'registration_endpoint' => url($oauthPrefix.'/register'),
                'response_types_supported' => ['code'],
                'code_challenge_methods_supported' => ['S256'],
                'grant_types_supported' => ['authorization_code'],
            ]);
        });

        Router::post($oauthPrefix.'/register', function (Request $request) {
            $clients = app("Laravel\Passport\ClientRepository");
            $payload = $request->json()->all();

            $client = $clients->createAuthorizationCodeGrantClient(
                name: $payload['client_name'],
                redirectUris: $payload['redirect_uris'],
                confidential: false,
                user: null,
                enableDeviceFlow: true,
            );

            return response()->json([
                'client_id' => $client->id,
                'redirect_uris' => $client->redirect_uris,
            ]);
        });
    }

    /**
     * Boot the MCP server.
     */
    private function bootServer(string $serverClass, callable $transportFactory)
    {
        $transport = $transportFactory();

        $server = new $serverClass;

        $server->connect($transport);

        return $transport->run();
    }
}
