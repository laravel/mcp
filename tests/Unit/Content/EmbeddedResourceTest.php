<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Content\EmbeddedResource;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('may be used in tools and prompts', function (): void {
    $embedded = new EmbeddedResource(
        uri: 'file:///notes/standup.md',
        text: '# Standup',
        mimeType: 'text/markdown',
    );

    $expected = [
        'type' => 'resource',
        'resource' => [
            'uri' => 'file:///notes/standup.md',
            'mimeType' => 'text/markdown',
            'text' => '# Standup',
        ],
    ];

    expect($embedded->toTool(new class extends Tool {}))->toEqual($expected)
        ->and($embedded->toPrompt(new class extends Prompt {}))->toEqual($expected);
});

it('may not be used in resources', function (): void {
    $embedded = new EmbeddedResource('file:///notes/standup.md', text: '# Standup');

    $resource = new class extends Resource
    {
        protected string $uri = 'file://store/items.json';
    };

    expect(fn (): array => $embedded->toResource($resource))
        ->toThrow(InvalidArgumentException::class, 'EmbeddedResource content may not be used in resources.');
});

it('base64 encodes blob content', function (): void {
    $embedded = new EmbeddedResource('file:///images/logo.png', blob: 'raw-bytes', mimeType: 'image/png');

    expect($embedded->toArray())->toEqual([
        'type' => 'resource',
        'resource' => [
            'uri' => 'file:///images/logo.png',
            'mimeType' => 'image/png',
            'blob' => base64_encode('raw-bytes'),
        ],
    ]);
});

it('omits optional fields when not provided', function (): void {
    $array = (new EmbeddedResource('file:///notes/standup.md', text: '# Standup'))->toArray();

    expect($array)
        ->not->toHaveKey('annotations')
        ->not->toHaveKey('_meta')
        ->and($array['resource'])
        ->not->toHaveKey('mimeType')
        ->not->toHaveKey('blob');
});

it('includes annotations and meta when provided', function (): void {
    $embedded = new EmbeddedResource(
        uri: 'file:///notes/standup.md',
        text: '# Standup',
        annotations: ['audience' => ['assistant'], 'priority' => 0.5],
    );
    $embedded->setMeta(['source' => 'notes']);

    expect($embedded->toArray())->toEqual([
        'type' => 'resource',
        'resource' => [
            'uri' => 'file:///notes/standup.md',
            'text' => '# Standup',
        ],
        'annotations' => ['audience' => ['assistant'], 'priority' => 0.5],
        '_meta' => ['source' => 'notes'],
    ]);
});

it('keeps empty content', function (?string $text, ?string $blob, string $key): void {
    $resource = (new EmbeddedResource('file:///notes/empty.txt', $text, $blob))->toArray()['resource'];

    expect($resource)->toHaveKey($key)
        ->and($resource[$key])->toBe('');
})->with([
    'text' => ['', null, 'text'],
    'blob' => [null, '', 'blob'],
]);

it('casts to string as its content', function (): void {
    expect((string) new EmbeddedResource('file:///notes/standup.md', text: '# Standup'))->toBe('# Standup')
        ->and((string) new EmbeddedResource('file:///images/logo.png', blob: 'raw-bytes'))->toBe('raw-bytes');
});

it('requires either text or blob content', function (?string $text, ?string $blob): void {
    expect(fn (): EmbeddedResource => new EmbeddedResource('file:///notes/standup.md', $text, $blob))
        ->toThrow(InvalidArgumentException::class, 'Embedded resources require either text or blob content, but not both.');
})->with([
    'neither' => [null, null],
    'both' => ['# Standup', 'raw-bytes'],
]);
