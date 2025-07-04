<?php

namespace Laravel\Mcp\Tests\Unit\Tools;

use Laravel\Mcp\Resources\Resource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

class ResourceTest extends TestCase
{
    private function makeResource(): Resource
    {
        return new DummyResource;
    }

    private function makeBlobResource(): Resource
    {
        return new class extends Resource
        {
            public string $type = 'blob';

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
    public function it_has_expected_default_values(): void
    {
        $resource = $this->makeResource();

        $this->assertSame('dummy-resource', $resource->name());
        $this->assertSame('Dummy Resource', $resource->title());
        $this->assertSame('file://resources/dummy-resource', $resource->uri());
        $this->assertSame('text/plain', $resource->mimeType());
    }
}
