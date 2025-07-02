<?php

declare(strict_types=1);

namespace Laravel\Mcp\Tests\Unit\Methods;

use Illuminate\Support\ItemNotFoundException;
use InvalidArgumentException;
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
        $readResource = new ReadResource;
        $context = $this->getServerContext();
        $context = $this->getServerContext([
            'resources' => [
                $resource,
            ],
        ]);
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

    #[Test]
    public function it_throws_exception_when_uri_is_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required parameter: uri');

        $readResource = new ReadResource;
        $context = $this->getServerContext();

        $jsonRpcRequest = new JsonRpcRequest(
            id: 1,
            method: 'resources/read',
            params: [] // intentionally omitting 'uri'
        );

        $readResource->handle($jsonRpcRequest, $context);
    }

    #[Test]
    public function it_throws_exception_when_resource_is_not_found(): void
    {
        $this->expectException(ItemNotFoundException::class);

        $readResource = new ReadResource;
        $context = $this->getServerContext();

        $jsonRpcRequest = new JsonRpcRequest(
            id: 1,
            method: 'resources/read',
            params: ['uri' => 'file://resources/non-existent']
        );

        $readResource->handle($jsonRpcRequest, $context);
    }
}
