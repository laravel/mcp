<?php

use Laravel\Mcp\Server\Content\StructuredContent;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('encodes content to resource payload with metadata', function (): void {
    $structuredContent = new StructuredContent(['name' => 'John', 'age' => 30]);
    $resource = new class extends Resource
    {
        protected string $uri = 'file://readme.txt';

        protected string $name = 'readme';

        protected string $title = 'Readme File';

        protected string $mimeType = 'application/json';
    };

    $payload = $structuredContent->toResource($resource);

    expect($payload)->toEqual([
        'json' => json_encode(['name' => 'John', 'age' => 30], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
        'uri' => 'file://readme.txt',
        'name' => 'readme',
        'title' => 'Readme File',
        'mimeType' => 'application/json',
    ]);
});

it('may be used in tools', function (): void {
    $structuredContent = new StructuredContent(['name' => 'John', 'age' => 30]);

    $payload = $structuredContent->toTool(new class extends Tool {});

    expect($payload)->toEqual([
        'name' => 'John',
        'age' => 30,
    ]);
});

it('may be used in prompts', function (): void {
    $structuredContent = new StructuredContent(['name' => 'John', 'age' => 30]);

    $payload = $structuredContent->toPrompt(new class extends Prompt {});

    expect($payload)->toEqual([
        'type' => 'text',
        'text' => json_encode(['name' => 'John', 'age' => 30], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
    ]);
});

it('casts to string as raw text', function (): void {
    $structuredContent = new StructuredContent(['name' => 'John', 'age' => 30]);

    expect((string) $structuredContent)->toBe(json_encode(['name' => 'John', 'age' => 30], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
});

it('converts to array with type and text', function (): void {
    $structuredContent = new StructuredContent(['name' => 'John', 'age' => 30]);

    expect($structuredContent->toArray())->toEqual([
        'type' => 'text',
        'text' => json_encode(['name' => 'John', 'age' => 30], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
    ]);
});
