<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void local(string $handle, string $serverClass)
 * @method static \Illuminate\Routing\Route web(string $handle, string $serverClass)
 * @method static callable|null getLocalServer(string $handle)
 * @method static string|null getWebServer(string $handle)
 *
 * @see \Laravel\Mcp\Server\Registrar
 */
class Mcp extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'mcp';
    }
}
