<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Laravel\Mcp\Server\Middleware\ReorderJsonAccept;
use Laravel\Mcp\Server\Transport\HttpTransport;
use Laravel\Mcp\Server\Transport\StdioTransport;

class Registrar
{
    /** @var array<string, callable> */
    protected array $localServers = [];

    /** @var array<string, Route> */
    protected array $httpServers = [];

    public function web(string $route, string $serverClass): Route
    {
        // https://modelcontextprotocol.io/specification/2025-06-18/basic/transports#listening-for-messages-from-the-server
        Router::get($route, fn () => response(status: 405));

        $route = Router::post($route, fn () => $this->bootServer(
            $serverClass,
            function () {
                $request = request();

                return new HttpTransport(
                    $request,
                    (string) $request->header('Mcp-Session-Id')
                );
            },
        ))
            ->name($this->routeName(ltrim($route, '/')))
            ->middleware([
                ReorderJsonAccept::class,
                AddWwwAuthenticateHeader::class,
            ]);

        $this->httpServers[$route->uri()] = $route;

        return $route;
    }

    public function local(string $handle, string $serverClass): void
    {
        $this->localServers[$handle] = fn () => $this->bootServer(
            $serverClass,
            fn () => new StdioTransport(
                Str::uuid()->toString(),
            )
        );
    }

    public function routeName(string $path): string
    {
        return 'mcp-server.'.Str::kebab(Str::replace('/', '-', $path));
    }

    public function getLocalServer(string $handle): ?callable
    {
        return $this->localServers[$handle] ?? null;
    }

    public function getWebServer(string $route): ?Route
    {
        return $this->httpServers[$route] ?? null;
    }

    /**
     * @return array<string, callable|Route>
     */
    public function servers(): array
    {
        return array_merge(
            $this->localServers,
            $this->httpServers,
        );
    }

    public function oauthRoutes(string $oauthPrefix = 'oauth'): void
    {
        Router::get('/.well-known/oauth-protected-resource', function () {
            return response()->json([
                'resource' => url('/'),
                'authorization_server' => url('/.well-known/oauth-authorization-server'),
            ]);
        })->name('mcp.oauth.protected-resource');

        Router::get('/.well-known/oauth-authorization-server', function () use ($oauthPrefix) {
            return response()->json([
                'issuer' => url('/'),
                'authorization_endpoint' => url($oauthPrefix.'/authorize'),
                'token_endpoint' => url($oauthPrefix.'/token'),
                'registration_endpoint' => url($oauthPrefix.'/register'),
                'response_types_supported' => ['code'],
                'code_challenge_methods_supported' => ['S256'],
                'supported_scopes' => ['mcp:use'],
                'grant_types_supported' => ['authorization_code', 'refresh_token'],
            ]);
        });

        Router::post($oauthPrefix.'/register', function (Request $request) {
            $clients = Container::getInstance()->make(
                "Laravel\Passport\ClientRepository"
            );

            $payload = $request->json()->all();

            $client = $clients->createAuthorizationCodeGrantClient(
                name: $payload['client_name'],
                redirectUris: $payload['redirect_uris'],
                confidential: false,
                user: null,
                enableDeviceFlow: false,
            );

            return response()->json([
                'client_id' => $client->id,
                'redirect_uris' => $client->redirect_uris,
                'scopes' => 'mcp:use',
            ]);
        });
    }

    protected function bootServer(string $serverClass, callable $transportFactory): mixed
    {
        $transport = $transportFactory();

        $server = new $serverClass;

        $server->connect($transport);

        return $transport->run();
    }
}
