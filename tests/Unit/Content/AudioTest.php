<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Content\Audio;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('may be used in tools', function (): void {
    $audio = new Audio('raw-audio-bytes', 'audio/mp3');

    $payload = $audio->toTool(new class extends Tool {});

    expect($payload)->toEqual([
        'type' => 'audio',
        'data' => base64_encode('raw-audio-bytes'),
        'mimeType' => 'audio/mp3',
    ]);
});

it('may be used in prompts', function (): void {
    $audio = new Audio('raw-audio-bytes', 'audio/mp3');

    $payload = $audio->toPrompt(new class extends Prompt {});

    expect($payload)->toEqual([
        'type' => 'audio',
        'data' => base64_encode('raw-audio-bytes'),
        'mimeType' => 'audio/mp3',
    ]);
});

it('may be used in resources', function (): void {
    $audio = new Audio('raw-audio-bytes', 'audio/mp3');

    $resource = new class extends Resource
    {
        protected string $uri = 'file://audio/clip.mp3';

        protected string $mimeType = 'audio/mp3';
    };

    $payload = $audio->toResource($resource);

    expect($payload)->toEqual([
        'blob' => base64_encode('raw-audio-bytes'),
        'uri' => 'file://audio/clip.mp3',
        'mimeType' => 'audio/mp3',
    ]);
});

it('casts to string as raw data', function (): void {
    $audio = new Audio('hello');

    expect((string) $audio)->toBe('hello');
});

it('converts to array with type, data and mimeType', function (): void {
    $audio = new Audio('bytes', 'audio/ogg');

    expect($audio->toArray())->toEqual([
        'type' => 'audio',
        'data' => base64_encode('bytes'),
        'mimeType' => 'audio/ogg',
    ]);
});

it('defaults mimeType to audio/wav', function (): void {
    $audio = new Audio('data');

    expect($audio->toArray())->toEqual([
        'type' => 'audio',
        'data' => base64_encode('data'),
        'mimeType' => 'audio/wav',
    ]);
});

it('supports meta via setMeta', function (): void {
    $audio = new Audio('binary-data');
    $audio->setMeta(['duration' => '3.5s']);

    expect($audio->toArray())->toMatchArray([
        'type' => 'audio',
        'data' => base64_encode('binary-data'),
        'mimeType' => 'audio/wav',
        '_meta' => ['duration' => '3.5s'],
    ]);
});

it('does not include meta if null', function (): void {
    $audio = new Audio('data');

    $array = $audio->toArray();

    expect($array)->toMatchArray([
        'type' => 'audio',
        'data' => base64_encode('data'),
        'mimeType' => 'audio/wav',
    ])->and($array)->not->toHaveKey('_meta');
});
