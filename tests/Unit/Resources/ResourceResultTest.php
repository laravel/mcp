<?php

namespace Laravel\Mcp\Tests\Unit\Resources;

use Laravel\Mcp\Resources\BlobResource;
use Laravel\Mcp\Resources\Resource;
use Laravel\Mcp\Resources\ResourceResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceResultTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_resource_result_for_text_resources(): void
    {
        $text = 'This is a test resource.';

        $resource = new class($text) extends Resource
        {
            public function __construct(private string $text)
            {
                // Keep default $type of 'text'
            }

            public function description(): string
            {
                return 'A test text resource.';
            }

            public function read(): string
            {
                return $this->text;
            }
        };

        $result = (new ResourceResult($resource))->toArray();

        $expected = [
            'contents' => [
                [
                    'uri' => $resource->uri(),
                    'name' => $resource->name(),
                    'title' => $resource->title(),
                    'mimeType' => $resource->mimeType(),
                    'text' => $text,
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function it_returns_a_valid_resource_result_for_blob_resources(): void
    {
        $binaryData = random_bytes(10);

        $resource = new class($binaryData) extends BlobResource
        {
            public function __construct(private string $data) {}

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
                return 'audio/mp3';
            }

            public function read(): string
            {
                return $this->data;
            }
        };

        $result = (new ResourceResult($resource))->toArray();

        $expected = [
            'contents' => [
                [
                    'uri' => 'file://resources/I_CAN_BE_OVERRIDDEN',
                    'name' => $resource->name(),
                    'title' => $resource->title(),
                    'mimeType' => 'audio/mp3',
                    'blob' => base64_encode($binaryData),
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }
}
