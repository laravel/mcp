<?php

use Laravel\Mcp\Server\Content\Link;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('encodes content to resource payload with metadata', function (): void {
    $text = new Link('More information', 'https://example.com/readme');
    $resource = new class extends Resource
    {
        protected string $uri = 'file://readme.txt';

        protected string $name = 'readme';

        protected string $title = 'Readme File';

        protected string $mimeType = 'text/plain';
    };

    $payload = $text->toResource($resource);

    expect($payload)->toEqual([
        'text' => 'More information',
        'href' => 'https://example.com/readme',
        'uri' => 'file://readme.txt',
        'name' => 'readme',
        'title' => 'Readme File',
        'mimeType' => 'text/plain',
    ]);
});

it('may be used in tools', function (): void {
    $text = new Link('More information', 'https://example.com/docs');

    $payload = $text->toTool(new class extends Tool {});

    expect($payload)->toEqual([
        'type' => 'link',
        'text' => 'More information',
        'href' => 'https://example.com/docs',
    ]);
});

it('may be used in prompts', function (): void {
    $text = new Link('Get documentation', 'https://example.com/docs');

    $payload = $text->toPrompt(new class extends Prompt {});

    expect($payload)->toEqual([
        'type' => 'link',
        'text' => 'Get documentation',
        'href' => 'https://example.com/docs',
    ]);
});

it('casts to string as raw text', function (): void {
    $text = new Link('More information', 'https://example.com/docs');

    expect((string) $text)->toBe('More information');
});

it('converts to array with type and text', function (): void {
    $text = new Link('More information', 'https://example.com/docs');

    expect($text->toArray())->toEqual([
        'type' => 'link',
        'text' => 'More information',
        'href' => 'https://example.com/docs',
    ]);
});
