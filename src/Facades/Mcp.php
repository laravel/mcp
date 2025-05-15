<?php

namespace Laravel\Mcp\Facades;

use Illuminate\Support\Facades\Facade;

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
