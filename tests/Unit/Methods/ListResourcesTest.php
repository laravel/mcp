<?php

declare(strict_types=1);

namespace Laravel\Mcp\Tests\Unit\Methods;

use Laravel\Mcp\Methods\ListResources;
use Laravel\Mcp\Resources\Resource;
use Laravel\Mcp\Tests\TestCase;
use Laravel\Mcp\Transport\JsonRpcRequest;
use PHPUnit\Framework\Attributes\Test;

// A concrete implementation of the abstract Resource class for test purposes.
class DummyResource extends Resource
{
    public function description(): string
    {
        return 'A test resource';
    }

    public function read(): string
    {
        return 'resource-content';
    }
}

class ListResourcesTest extends TestCase
{
    private function makeResource(): Resource
    {
        return new DummyResource();
    }

    #[Test]
    public function it_returns_a_valid_empty_list_resources_response(): void
    {
        $listResources = new ListResources();
        $context = $this->getServerContext();
        $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/list', params: []);

        $this->assertMethodResult([
            'resources' => [],
        ], $listResources->handle($jsonRpcRequest, $context));
    }

    #[Test]
    public function it_returns_a_valid_populated_list_resources_response(): void
    {
        $listResources = new ListResources();
        $context = $this->getServerContext();
        $context->resources = [
            $this->makeResource(),
        ];
        $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/list', params: []);

        $this->assertMethodResult([
            'resources' => [
                [
                    'name' => 'dummy-resource',
                    'title' => 'Dummy Resource',
                    'description' => 'A test resource',
                    'uri' => 'file://resources/dummy-resource',
                    'mimeType' => 'text/plain',
                ],
            ],
        ], $listResources->handle($jsonRpcRequest, $context));
    }
}
