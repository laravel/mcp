<?php

declare(strict_types=1);

namespace Laravel\Mcp\Tests\Unit\Methods;

use Laravel\Mcp\Server\Methods\ListResources;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ListResourcesTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_empty_list_resources_response(): void
    {
        $listResources = new ListResources;
        $context = $this->getServerContext();
        $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/list', params: []);
        $result = $listResources->handle($jsonRpcRequest, $context);

        $this->assertMethodResult([
            'resources' => [],
        ], $result);
    }

    #[Test]
    public function it_returns_a_valid_populated_list_resources_response(): void
    {
        $listResources = new ListResources;
        $resource = $this->makeResource();

        $context = $this->getServerContext([
            'resources' => [
                $resource,
            ],
        ]);
        $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/list', params: []);

        $this->assertMethodResult([
            'resources' => [
                [
                    'name' => $resource->name(),
                    'title' => $resource->title(),
                    'description' => $resource->description(),
                    'uri' => $resource->uri(),
                    'mimeType' => $resource->mimeType(),
                ],
            ],
        ], $listResources->handle($jsonRpcRequest, $context));
    }
}
