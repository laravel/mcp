<?php

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

it('returns a valid resource result for text resources', function (): void {
    $resource = new class extends Resource
    {
        public function description(): string
        {
            return 'A test text resource.';
        }

        public function handle(): Response
        {
            return Response::text('This is a test resource.');
        }
    };

    $result = $resource->handle();

    $expected = [
        'text' => 'This is a test resource.',
        'uri' => $resource->uri(),
        'name' => $resource->name(),
        'title' => $resource->title(),
        'mimeType' => $resource->mimeType(),
    ];

    expect($result->content()->toResource($resource))->toBe($expected);
});

it('returns a valid resource result for binary resources', function (): void {
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

        public function handle(): Response
        {
            return Response::blob(file_get_contents(__DIR__.'/../../Fixtures/binary.png'));
        }
    };

    $result = $resource->handle();

    $expected = [
        'blob' => base64_encode($binaryData),
        'uri' => 'file://resources/I_CAN_BE_OVERRIDDEN',
        'name' => $resource->name(),
        'title' => $resource->title(),
        'mimeType' => 'image/png',
    ];

    expect($result->content()->toResource($resource))->toBe($expected);
});

it('handles a text content object returned from read', function (): void {
    $resource = new class extends Resource
    {
        public function description(): string
        {
            return 'A test resource.';
        }

        public function handle(): Response
        {
            return Response::text('This is a test resource.');
        }
    };

    $result = $resource->handle();

    $expected =
            [
                'text' => 'This is a test resource.',

                'uri' => $resource->uri(),
                'name' => $resource->name(),
                'title' => $resource->title(),
                'mimeType' => $resource->mimeType(),
            ];

    expect($result->content()->toResource($resource))->toBe($expected);
});

it('handles a blob content object returned from read', function (): void {
    $resource = new class extends Resource
    {
        public function description(): string
        {
            return 'A test resource.';
        }

        public function handle(): Response
        {
            return Response::blob('This is a test resource.');
        }
    };

    $result = $resource->handle();

    $expected = [
        'blob' => base64_encode('This is a test resource.'),

        'uri' => $resource->uri(),
        'name' => $resource->name(),
        'title' => $resource->title(),
        'mimeType' => $resource->mimeType(),
    ];

    expect($result->content()->toResource($resource))->toBe($expected);
});

test('description property works as expected', function (): void {
    $resource = new class extends Resource
    {
        public function description(): string
        {
            return 'A test resource.';
        }

        public function handle(): string
        {
            return 'This is a test resource.';
        }
    };
    expect($resource->description())->toBe('A test resource.');
});
