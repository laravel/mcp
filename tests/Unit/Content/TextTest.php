<?php

use Laravel\Mcp\Server\Content\Text;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('encodes content to resource payload with metadata', function (): void {
    $text = new Text('Hello world');
    $resource = new class extends Resource
    {
        protected string $uri = 'file://readme.txt';

        protected string $name = 'readme';

        protected string $title = 'Readme File';

        protected string $mimeType = 'text/plain';
    };

    $payload = $text->toResource($resource);

    expect($payload)->toEqual([
        'text' => 'Hello world',
        'uri' => 'file://readme.txt',
        'name' => 'readme',
        'title' => 'Readme File',
        'mimeType' => 'text/plain',
    ]);
});

it('may be used in tools', function (): void {
    $text = new Text('Run me');

    $payload = $text->toTool(new class extends Tool {});

    expect($payload)->toEqual([
        'type' => 'text',
        'text' => 'Run me',
    ]);
});

it('may be used in prompts', function (): void {
    $text = new Text('Say hi');

    $payload = $text->toPrompt(new class extends Prompt {});

    expect($payload)->toEqual([
        'type' => 'text',
        'text' => 'Say hi',
    ]);
});

it('casts to string as raw text', function (): void {
    $text = new Text('plain');

    expect((string) $text)->toBe('plain');
});

it('converts to array with type and text', function (): void {
    $text = new Text('abc');

    expect($text->toArray())->toEqual([
        'type' => 'text',
        'text' => 'abc',
    ]);
});
