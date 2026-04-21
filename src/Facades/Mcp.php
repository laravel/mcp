<?php

declare(strict_types=1);

namespace Laravel\Mcp\Facades;

use Illuminate\Support\Facades\Facade;
use Laravel\Mcp\Server\Registrar;

/**
 * @method static \Illuminate\Routing\Route web(string $route, class-string<\Laravel\Mcp\Server> $serverClass)
 * @method static void local(string $handle, class-string<\Laravel\Mcp\Server> $serverClass)
 * @method static callable|null getLocalServer(string $handle)
 * @method static \Illuminate\Routing\Route|null getWebServer(string $route)
 * @method static array<string, callable|\Illuminate\Routing\Route> servers()
 * @method static void oauthRoutes(string $oauthPrefix = 'oauth')
 * @method static array<string, string> ensureMcpScope()
 *
 * @see Registrar
 */
class Mcp extends Facade
{
    /**
     * @return class-string<Registrar>
     */
    protected static function getFacadeAccessor(): string
    {
        return Registrar::class;
    }
}
