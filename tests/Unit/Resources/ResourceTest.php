<?php

namespace Laravel\Mcp\Tests\Unit\Resources;

use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Resources\Content\Blob;
use Laravel\Mcp\Server\Resources\Content\Text;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_resource_result_for_text_resources(): void
    {
        $resource = new class extends Resource
        {
            public function description(): string
            {
                return 'A test text resource.';
            }

            public function read(): string
            {
                return 'This is a test resource.';
            }
        };

        $result = $resource->handle();

        $expected = [
            'contents' => [
                [
                    'uri' => $resource->uri(),
                    'name' => $resource->name(),
                    'title' => $resource->title(),
                    'mimeType' => $resource->mimeType(),
                    'text' => 'This is a test resource.',
                ],
            ],
        ];

        $this->assertSame($expected, $result->toArray());
    }

    #[Test]
    public function it_returns_a_valid_resource_result_for_binary_resources(): void
    {
        $binaryData = file_get_contents(__DIR__.'/../../Fixtures/binary.png');

        $resource = new class extends Resource
        {
            public function description(): string
            {
                return 'A test blob resource.';
            }

            public function uri(): string
            {
                return 'file://resources/I_CAN_BE_OVERRIDDEN';
            }

            public function mimeType(): string
            {
                return 'image/png';
            }

            public function read(): string
            {
                return file_get_contents(__DIR__.'/../../Fixtures/binary.png');
            }
        };

        $result = $resource->handle()->toArray();

        $expected = [
            'contents' => [
                [
                    'uri' => 'file://resources/I_CAN_BE_OVERRIDDEN',
                    'name' => $resource->name(),
                    'title' => $resource->title(),
                    'mimeType' => 'image/png',
                    'blob' => base64_encode($binaryData),
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function it_handles_a_text_content_object_returned_from_read(): void
    {
        $resource = new class extends Resource
        {
            public function description(): string
            {
                return 'A test resource.';
            }

            public function read(): Text
            {
                return new Text('This is a test resource.');
            }
        };

        $result = $resource->handle()->toArray();

        $expected = [
            'contents' => [
                [
                    'uri' => $resource->uri(),
                    'name' => $resource->name(),
                    'title' => $resource->title(),
                    'mimeType' => $resource->mimeType(),
                    'text' => 'This is a test resource.',
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function it_handles_a_blob_content_object_returned_from_read(): void
    {
        $resource = new class extends Resource
        {
            public function description(): string
            {
                return 'A test resource.';
            }

            public function read(): Blob
            {
                return new Blob('This is a test resource.');
            }
        };

        $result = $resource->handle()->toArray();

        $expected = [
            'contents' => [
                [
                    'uri' => $resource->uri(),
                    'name' => $resource->name(),
                    'title' => $resource->title(),
                    'mimeType' => $resource->mimeType(),
                    'blob' => base64_encode('This is a test resource.'),
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function it_only_calls_read_once(): void
    {
        $resource = $this->getMockBuilder(Resource::class)
            ->onlyMethods(['read', 'description'])
            ->getMock();

        $resource->method('description')
            ->willReturn('A test resource.');

        $resource->expects($this->once())
            ->method('read')
            ->willReturn('This is a test resource.');

        $result = $resource->handle();

        $this->assertSame('This is a test resource.', $result->toArray()['contents'][0]['text']);
    }

    #[Test]
    public function description_property_works_as_expected(): void
    {
        $resource = new class extends Resource
        {
            protected string $description = 'A test resource.';

            public function read(): string
            {
                return 'This is a test resource.';
            }
        };

        $this->assertSame('A test resource.', $resource->description());
    }
}
