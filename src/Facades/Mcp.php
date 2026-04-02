<?php

declare(strict_types=1);

namespace Laravel\Mcp\Facades;

use Illuminate\Support\Facades\Facade;
use Laravel\Mcp\Server\Registrar;

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
