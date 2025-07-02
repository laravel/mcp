<?php

namespace Laravel\Mcp\Tests;

use Laravel\Mcp\McpServiceProvider;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
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

    protected function getServerContext(): ServerContext
    {
        return new ServerContext(
            supportedProtocolVersions: [],
            serverCapabilities: [],
            serverName: 'test-server',
            serverVersion: '1.0.0',
            instructions: 'test-instructions',
            tools: [],
            resources: [],
            maxPaginationLength: 3,
            defaultPaginationLength: 3,
        );
    }

    protected function assertMethodResult(array $expected, JsonRpcResponse $response): void
    {
        $result = data_get($response->toArray(), 'result');

        $this->assertEquals($expected, $result);
    }

    protected function assertPartialMethodResult(array $expected, JsonRpcResponse $response): void
    {
        $result = data_get($response->toArray(), 'result');

        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $result);
            if (is_array($value)) {
                $this->assertPartialArray($value, $result[$key]);
            } else {
                $this->assertEquals($value, $result[$key]);
            }
        }
    }

    protected function assertPartialArray(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $actual);
            if (is_array($value)) {
                $this->assertPartialArray($value, $actual[$key]);
            } else {
                $this->assertEquals($value, $actual[$key]);
            }
        }
    }
}
