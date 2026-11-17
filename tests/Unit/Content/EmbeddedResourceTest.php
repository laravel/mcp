<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Content\EmbeddedResource;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('may be used in tools and prompts', function (): void {
    $embedded = new EmbeddedResource([
        'uri' => 'file:///notes/standup.md',
        'mimeType' => 'text/markdown',
        'text' => '# Standup',
    ]);

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
    $embedded = new EmbeddedResource(['uri' => 'file:///notes/standup.md', 'text' => '# Standup']);

    $resource = new class extends Resource
    {
        protected string $uri = 'file://store/items.json';
    };

    expect(fn (): array => $embedded->toResource($resource))
        ->toThrow(InvalidArgumentException::class, 'EmbeddedResource content may not be used in resources.');
});

it('includes annotations and meta when provided', function (): void {
    $embedded = new EmbeddedResource(
        ['uri' => 'file:///notes/standup.md', 'text' => '# Standup'],
        ['audience' => ['assistant'], 'priority' => 0.5],
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

it('omits annotations and meta when not provided', function (): void {
    expect((new EmbeddedResource(['uri' => 'file:///notes/standup.md', 'text' => '# Standup']))->toArray())
        ->not->toHaveKey('annotations')
        ->not->toHaveKey('_meta');
});

it('casts to its text, or its uri for blob contents', function (): void {
    expect((string) new EmbeddedResource(['uri' => 'file:///notes/standup.md', 'text' => '# Standup']))->toBe('# Standup')
        ->and((string) new EmbeddedResource(['uri' => 'file:///images/logo.png', 'blob' => 'YmluYXJ5']))->toBe('file:///images/logo.png');
});

it('requires a uri', function (): void {
    expect(fn (): EmbeddedResource => new EmbeddedResource(['text' => '# Standup']))
        ->toThrow(InvalidArgumentException::class, 'Embedded resource contents require a [uri].');
});

it('requires either text or blob contents', function (array $contents): void {
    expect(fn (): EmbeddedResource => new EmbeddedResource(['uri' => 'file:///notes/standup.md', ...$contents]))
        ->toThrow(InvalidArgumentException::class, 'Embedded resource contents require either [text] or [blob], but not both.');
})->with([
    'neither' => [[]],
    'both' => [['text' => '# Standup', 'blob' => 'YmluYXJ5']],
]);
