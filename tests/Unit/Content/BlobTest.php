<?php

use Laravel\Mcp\Server\Content\Blob;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('encodes content to resource payload with metadata', function (): void {
    $blob = new Blob('raw-bytes');
    $resource = new class extends Resource
    {
        protected string $uri = 'file://avatar.png';

        protected string $name = 'avatar';

        protected string $title = 'User Avatar';

        protected string $mimeType = 'image/png';
    };

    $payload = $blob->toResource($resource);

    expect($payload)->toEqual([
        'blob' => base64_encode('raw-bytes'),
        'uri' => 'file://avatar.png',
        'name' => 'avatar',
        'title' => 'User Avatar',
        'mimeType' => 'image/png',
    ]);
});

it('preserves meta when converting to a resource payload', function (): void {
    $blob = new Blob('raw-bytes');
    $blob->setMeta(['encoding' => 'base64']);

    $resource = new class extends Resource
    {
        protected string $uri = 'file://avatar.png';

        protected string $name = 'avatar';

        protected string $title = 'User Avatar';

        protected string $mimeType = 'image/png';
    };

    $payload = $blob->toResource($resource);

    expect($payload)->toEqual([
        'blob' => base64_encode('raw-bytes'),
        'uri' => 'file://avatar.png',
        'name' => 'avatar',
        'title' => 'User Avatar',
        'mimeType' => 'image/png',
        '_meta' => ['encoding' => 'base64'],
    ]);
});

it('throws when used in tools', function (): void {
    $blob = new Blob('anything');

    $blob->toTool(new class extends Tool {});
})->throws(InvalidArgumentException::class, 'Blob content may not be used in tools.');

it('throws when used in prompts', function (): void {
    $blob = new Blob('anything');

    $blob->toPrompt(new class extends Prompt {});
})->throws(InvalidArgumentException::class, 'Blob content may not be used in prompts.');

it('casts to string as raw content', function (): void {
    $blob = new Blob('hello');

    expect((string) $blob)->toBe('hello');
});

it('converts to array with type and raw blob', function (): void {
    $blob = new Blob('bytes');

    expect($blob->toArray())->toEqual([
        'type' => 'blob',
        'blob' => 'bytes',
    ]);
});

it('supports meta via setMeta', function (): void {
    $blob = new Blob('binary-data');
    $blob->setMeta(['encoding' => 'base64']);

    expect($blob->toArray())->toEqual([
        'type' => 'blob',
        'blob' => 'binary-data',
        '_meta' => ['encoding' => 'base64'],
    ]);
});

it('does not include meta if null', function (): void {
    $blob = new Blob('data');

    expect($blob->toArray())->toEqual([
        'type' => 'blob',
        'blob' => 'data',
    ]);
});
