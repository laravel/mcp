<?php

use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Resources\Content\Blob;
use Laravel\Mcp\Server\Resources\Content\Text;

it('returns a valid resource result for text resources', function () {
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

    expect($result->toArray())->toBe($expected);
});

it('returns a valid resource result for binary resources', function () {
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

    expect($result)->toBe($expected);
});

it('handles a text content object returned from read', function () {
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

    expect($result)->toBe($expected);
});

it('handles a blob content object returned from read', function () {
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

    expect($result)->toBe($expected);
});

it('only calls read once', function () {
    $resource = $this->getMockBuilder(Resource::class)
        ->onlyMethods(['read', 'description'])
        ->getMock();

    $resource->method('description')
        ->willReturn('A test resource.');

    $resource->expects($this->once())
        ->method('read')
        ->willReturn('This is a test resource.');

    $result = $resource->handle();

    expect($result->toArray()['contents'][0]['text'])->toBe('This is a test resource.');
});

test('description property works as expected', function () {
    $resource = new class extends Resource
    {
        public function description(): string
        {
            return 'A test resource.';
        }

        public function read(): string
        {
            return 'This is a test resource.';
        }
    };
    expect($resource->description())->toBe('A test resource.');
});
