<?php

declare(strict_types=1);

namespace Laravel\Mcp\Facades;

use Laravel\Mcp\Server;
use Illuminate\Routing\Route;
use Laravel\Mcp\Server\Registrar;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Route web(string $route, class-string<Server> $serverClass)
 * @method static void local(string $handle, class-string<Server> $serverClass)
 * @method static callable|null getLocalServer(string $handle)
 * @method static Route|null getWebServer(string $route)
 * @method static array<string, callable|Route> servers()
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
