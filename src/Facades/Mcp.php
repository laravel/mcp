<?php

declare(strict_types=1);

namespace Laravel\Mcp\Facades;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Facade;
use Laravel\Mcp\Server\Registrar;

/**
 * @method static void local(string $handle, string $serverClass)
 * @method static Route web(string $route, string $serverClass)
 * @method static callable|null getLocalServer(string $handle)
 * @method static Route|null getWebServer(string $route)
 * @method static array<string, callable|Route> servers()
 * @method static void oauthRoutes(string $oauthPrefix = 'oauth')
 * @method static array<string, string> ensureMcpScope()
 *
 * @see \Laravel\Mcp\Server\Registrar
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
