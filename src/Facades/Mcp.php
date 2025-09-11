<?php

declare(strict_types=1);

namespace Laravel\Mcp\Facades;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Facade;
use Laravel\Mcp\Server\Registrar;

/**
 * @method static void local(string $handle, string $serverClass)
 * @method static Route web(string $handle, string $serverClass)
 * @method static callable|null getLocalServer(string $handle)
 * @method static string|null getWebServer(string $handle)
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
