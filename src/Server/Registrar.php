<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Exception;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Middleware\ReorderJsonAccept;
use Laravel\Mcp\Server\Transport\HttpTransport;
use Laravel\Mcp\Server\Transport\StdioTransport;

class Registrar
{
    /** @var array<string, callable> */
    protected array $localServers = [];

    /** @var array<string, string> */
    protected array $httpServers = [];

    /** @var array<string, string> */
    protected array $grantTypes = ['authorization_code', 'refresh_token']; // TODO: Move auth/oauth into own classes

    protected string $clientNamePrefix = '[MCP] ';

    public function web(string $route, string $serverClass): Route
    {
        $this->httpServers[$route] = $serverClass;

        // https://modelcontextprotocol.io/specification/2025-06-18/basic/transports#listening-for-messages-from-the-server
        Router::get($route, fn () => response(status: 405));

        $route = Router::post($route, fn () => $this->bootServer(
            $serverClass,
            fn () => new HttpTransport(request())
        ))
            ->name($this->routeName($route))
            ->middleware(ReorderJsonAccept::class); // TODO: Do we need this if it's public?

        return $route;
    }

    public function local(string $handle, string $serverClass): void
    {
        $this->localServers[$handle] = fn () => $this->bootServer(
            $serverClass,
            fn () => new StdioTransport
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

    public function getWebServer(string $handle): ?string
    {
        return $this->httpServers[$handle] ?? null;
    }

    public function oauthRoutes(): void
    {
        if (! class_exists('\Laravel\Passport\ClientRepository')) {
            throw new Exception('Laravel Passport is not installed. Please install it to use OAuth.');
        }

        // Add OAuth paths to Laravel's CORS config (local only)
        // TODO: Move to route names
        $this->appendToCorsConfig([
            '/.well-known/oauth-protected-resource',
            '/.well-known/oauth-authorization-server',
            '/oauth/register',
            '/oauth/token',
            // TODO: Use configured routes in case changed - route('passport.token', absolute: false),
        ]);

        $this->maybeAddMcpScope();
    }

    protected function appendToCorsConfig(array $paths): void
    {
        // Only safe in local environment
        if (! app()->environment('local')) {
            return;
        }

        $corsConfig = config('cors', []);
        $existingPaths = $corsConfig['paths'] ?? [];
        $exitingOrigins = $corsConfig['allowed_origins'] ?? [];

        // Merge OAuth paths without duplicates
        $corsConfig['paths'] = array_unique(array_merge($existingPaths, $paths));
        $corsConfig['allowed_origins'] = array_unique(array_merge($exitingOrigins, ['http://localhost:6274'])); // Allow MCP Inspector

        config(['cors' => $corsConfig]);
    }

    protected function maybeAddMcpScope(): array
    {
        $current = \Laravel\Passport\Passport::$scopes ?? [];

        if (! array_key_exists('mcp:use', $current)) {
            $current['mcp:use'] = 'Use MCP server';
            \Laravel\Passport\Passport::tokensCan($current);
        }

        return $current;
    }

    protected function bootServer(string $serverClass, callable $transportFactory): mixed
    {
        $transport = $transportFactory();

        $server = new $serverClass;

        $server->connect($transport);

        return $transport->run();
    }
}
