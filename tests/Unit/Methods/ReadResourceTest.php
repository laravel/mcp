<?php

declare(strict_types=1);

namespace Laravel\Mcp\Tests\Unit\Methods;

use Laravel\Mcp\Methods\ReadResource;
use Laravel\Mcp\Resources\Resource;
use Laravel\Mcp\Tests\TestCase;
use Laravel\Mcp\Transport\JsonRpcRequest;
use PHPUnit\Framework\Attributes\Test;

class ReadResourceTest extends TestCase
{
    private function makeResource(): Resource
    {
        return new class extends Resource
        {
            public function description(): string
            {
                return 'A test resource';
            }

            public function read(): string
            {
                return 'resource-content';
            }
        };
    }

    #[Test]
    public function it_returns_a_valid_resource_result(): void
    {
        $resource = $this->makeResource();
        $readResource = new ReadResource();
        $context = $this->getServerContext();
        $context->resources = [
            $resource,
        ];
        $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/read', params: ['uri' => $resource->uri()]);
        $resourceResult = $readResource->handle($jsonRpcRequest, $context);

        $this->assertPartialMethodResult([
            'contents' => [
                [
                    'text' => 'resource-content',
                ],
            ],
        ], $resourceResult);
    }

    #[Test]
    public function it_returns_a_valid_resource_result_for_blob_resources(): void
    {
        $resource = $this->makeResource();

        $this->assertSame('resource-content', $resource->read());
    }
}
