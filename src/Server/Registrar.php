<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
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

    /**
     * @param  class-string<Server>  $serverClass
     */
    public function web(string $route, string $serverClass): Route
    {
        // https://modelcontextprotocol.io/specification/2025-06-18/basic/transports#listening-for-messages-from-the-server
        Router::get($route, fn (): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response(status: 405));

        $route = Router::post($route, fn (): mixed => static::startServer(
            $serverClass,
            fn (): HttpTransport => new HttpTransport(
                $request = request(),
                // @phpstan-ignore-next-line
                (string) $request->header('MCP-Session-Id')
            ),
        ))->middleware([
            ReorderJsonAccept::class,
            AddWwwAuthenticateHeader::class,
        ]);

        assert($route instanceof Route);

        $this->httpServers[$route->uri()] = $route;

        return $route;
    }

    /**
     * @param  class-string<Server>  $serverClass
     */
    public function local(string $handle, string $serverClass): void
    {
        $this->localServers[$handle] = fn (): mixed => static::startServer($serverClass, fn (): StdioTransport => new StdioTransport(
            Str::uuid()->toString(),
        ));
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
        static::ensureMcpScope();
        Router::get('/.well-known/oauth-protected-resource/{path?}', fn (?string $path = '') => response()->json([
            'resource' => url('/'.$path),
            'authorization_servers' => [url('/'.$path)],
            'scopes_supported' => ['mcp:use'],
        ]))->where('path', '.*')->name('mcp.oauth.protected-resource');

        Router::get('/.well-known/oauth-authorization-server/{path?}', fn (?string $path = '') => response()->json([
            'issuer' => url('/'.$path),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'registration_endpoint' => url($oauthPrefix.'/register'),
            'response_types_supported' => ['code'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => ['mcp:use'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
        ]))->where('path', '.*')->name('mcp.oauth.authorization-server');

        Router::post($oauthPrefix.'/register', OAuthRegisterController::class);
    }

    /**
     * @return array<string, string>
     */
    public static function ensureMcpScope(): array
    {
        if (class_exists('Laravel\Passport\Passport') === false) {
            return [];
        }

        $current = \Laravel\Passport\Passport::$scopes ?? [];

        if (! array_key_exists('mcp:use', $current)) {
            $current['mcp:use'] = 'Use MCP server';
            \Laravel\Passport\Passport::tokensCan($current);
        }

        return $current;
    }

    /**
     * @param  class-string<Server>  $serverClass
     * @param  callable(): Transport  $transportFactory
     */
    protected static function startServer(string $serverClass, callable $transportFactory): mixed
    {
        $transport = $transportFactory();

        $server = Container::getInstance()->make($serverClass, [
            'transport' => $transport,
        ]);

        $server->start();

        return $transport->run();
    }
}
