<?php

namespace Laravel\Mcp\Tests;

use Laravel\Mcp\McpServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Workbench\App\Providers\WorkbenchServiceProvider;

abstract class TestCase extends TestbenchTestCase
{
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            McpServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }
}
