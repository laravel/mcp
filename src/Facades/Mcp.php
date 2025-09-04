<?php

declare(strict_types=1);

namespace Laravel\Mcp\Facades;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void local(string $handle, string $serverClass)
 * @method static Route web(string $handle, string $serverClass)
 * @method static callable|null getLocalServer(string $handle)
 * @method static string|null getWebServer(string $handle)
 *
 * @see \Laravel\Mcp\Server\Registrar
 */
class Mcp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mcp';
    }
}
