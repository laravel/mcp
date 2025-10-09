<?php

namespace Tests;

use Laravel\Mcp\Server\Contracts\Resources\Content;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Workbench\App\Providers\WorkbenchServiceProvider;

abstract class TestCase extends TestbenchTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            McpServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }

    protected function getServerContext(array $properties = []): ServerContext
    {
        $properties = array_merge([
            'supportedProtocolVersions' => [],
            'serverCapabilities' => [],
            'serverName' => 'test-server',
            'serverVersion' => '1.0.0',
            'instructions' => 'test-instructions',
            'maxPaginationLength' => 3,
            'defaultPaginationLength' => 3,
            'tools' => [],
            'resources' => [],
            'prompts' => [],
        ], $properties);

        return new ServerContext(...$properties);
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

    protected function makeResource(
        string|Content $content = 'resource-content',
        string $description = 'A test resource',
        array $overrides = [],
    ): Resource {
        return new class($content, $description, $overrides) extends Resource
        {
            public function __construct(
                private string $contentValue,
                private string $desc,
                private array $overrides,
            ) {
                if ($this->overrides['uri'] ?? false) {
                    $this->uri = $this->overrides['uri'];
                }

                if ($this->overrides['mimeType'] ?? false) {
                    $this->mimeType = $this->overrides['mimeType'];
                }
            }

            public function description(): string
            {
                return $this->desc;
            }

            public function handle(): string
            {
                return $this->contentValue;
            }
        };
    }

    protected function makeBinaryResource(
        string $filePath,
        string $description = 'A binary resource',
        array $overrides = [],
    ): Resource {
        $content = file_get_contents($filePath);
        $overrides['mimeType'] ??= mime_content_type($filePath) ?: 'application/octet-stream';

        return $this->makeResource($content, $description, $overrides);
    }
}
