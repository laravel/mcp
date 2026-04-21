<?php

declare(strict_types=1);

namespace Laravel\Mcp\Facades;

use Illuminate\Support\Facades\Facade;
use Laravel\Mcp\Server\Registrar;

/**
 * @method static \Illuminate\Routing\Route web(string $route, string $serverClass)
 * @method static void local(string $handle, string $serverClass)
 * @method static callable|null getLocalServer(string $handle)
 * @method static \Illuminate\Routing\Route|null getWebServer(string $route)
 * @method static array servers()
 * @method static void oauthRoutes(string $oauthPrefix = 'oauth')
 * @method static array ensureMcpScope()
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
