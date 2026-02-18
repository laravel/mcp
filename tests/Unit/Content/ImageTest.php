<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Content\Image;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('may be used in tools', function (): void {
    $image = new Image('raw-image-bytes', 'image/jpeg');

    $payload = $image->toTool(new class extends Tool {});

    expect($payload)->toEqual([
        'type' => 'image',
        'data' => base64_encode('raw-image-bytes'),
        'mimeType' => 'image/jpeg',
    ]);
});

it('may be used in prompts', function (): void {
    $image = new Image('raw-image-bytes', 'image/jpeg');

    $payload = $image->toPrompt(new class extends Prompt {});

    expect($payload)->toEqual([
        'type' => 'image',
        'data' => base64_encode('raw-image-bytes'),
        'mimeType' => 'image/jpeg',
    ]);
});

it('may be used in resources', function (): void {
    $image = new Image('raw-image-bytes', 'image/jpeg');

    $resource = new class extends Resource
    {
        protected string $uri = 'file://images/photo.jpg';

        protected string $mimeType = 'image/jpeg';
    };

    $payload = $image->toResource($resource);

    expect($payload)->toEqual([
        'blob' => base64_encode('raw-image-bytes'),
        'uri' => 'file://images/photo.jpg',
        'mimeType' => 'image/jpeg',
    ]);
});

it('casts to string as raw data', function (): void {
    $image = new Image('hello');

    expect((string) $image)->toBe('hello');
});

it('converts to array with type, data and mimeType', function (): void {
    $image = new Image('bytes', 'image/webp');

    expect($image->toArray())->toEqual([
        'type' => 'image',
        'data' => base64_encode('bytes'),
        'mimeType' => 'image/webp',
    ]);
});

it('defaults mimeType to image/png', function (): void {
    $image = new Image('data');

    expect($image->toArray())->toEqual([
        'type' => 'image',
        'data' => base64_encode('data'),
        'mimeType' => 'image/png',
    ]);
});

it('supports meta via setMeta', function (): void {
    $image = new Image('binary-data');
    $image->setMeta(['source' => 'camera']);

    expect($image->toArray())->toMatchArray([
        'type' => 'image',
        'data' => base64_encode('binary-data'),
        'mimeType' => 'image/png',
        '_meta' => ['source' => 'camera'],
    ]);
});

it('does not include meta if null', function (): void {
    $image = new Image('data');

    $array = $image->toArray();

    expect($array)->toMatchArray([
        'type' => 'image',
        'data' => base64_encode('data'),
        'mimeType' => 'image/png',
    ])->and($array)->not->toHaveKey('_meta');
});
