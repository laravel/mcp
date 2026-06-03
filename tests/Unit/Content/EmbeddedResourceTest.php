<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Content\EmbeddedResource;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('may be used in tools and prompts', function (): void {
    $embeddedResource = new EmbeddedResource([
        'uri' => 'file://resources/summary.md',
        'text' => 'Session summary',
        'mimeType' => 'text/markdown',
    ]);

    $expected = [
        'type' => 'resource',
        'resource' => [
            'uri' => 'file://resources/summary.md',
            'text' => 'Session summary',
            'mimeType' => 'text/markdown',
        ],
    ];

    expect($embeddedResource->toTool(new class extends Tool {}))->toEqual($expected)
        ->and($embeddedResource->toPrompt(new class extends Prompt {}))->toEqual($expected);
});

it('may not be used in resources', function (): void {
    $embeddedResource = new EmbeddedResource([
        'uri' => 'file://resources/summary.md',
        'text' => 'Session summary',
        'mimeType' => 'text/markdown',
    ]);

    $resource = new class extends Resource
    {
        protected string $uri = 'file://store/items.json';

        protected string $mimeType = 'application/json';
    };

    expect(fn (): array => $embeddedResource->toResource($resource))
        ->toThrow(InvalidArgumentException::class, 'EmbeddedResource content may not be used in resources.');
});

it('casts to string as the embedded text', function (): void {
    $embeddedResource = new EmbeddedResource([
        'uri' => 'file://resources/summary.md',
        'text' => 'Session summary',
        'mimeType' => 'text/markdown',
    ]);

    expect((string) $embeddedResource)->toBe('Session summary');
});
