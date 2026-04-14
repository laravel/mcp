<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Content\Audio;
use Laravel\Mcp\Server\Content\Blob;
use Laravel\Mcp\Server\Content\Image;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Content\Text;

it('creates a notification response', function (): void {
    $response = Response::notification('test.method', ['key' => 'value']);

    expect($response->content())->toBeInstanceOf(Notification::class)
        ->and($response->isNotification())->toBeTrue()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates a text response', function (): void {
    $response = Response::text('Hello world');

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates a blob response', function (): void {
    $response = Response::blob('binary content');

    expect($response->content())->toBeInstanceOf(Blob::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates an error response', function (): void {
    $response = Response::error('Something went wrong');

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeTrue()
        ->and($response->role())->toBe(Role::User);
});

it('creates an image response', function (): void {
    $response = Response::image('raw-bytes');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates an image response with custom mime type', function (): void {
    $response = Response::image('raw-bytes', 'image/webp');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and($response->content()->toArray()['mimeType'])->toBe('image/webp');
});

it('creates an audio response', function (): void {
    $response = Response::audio('raw-bytes');

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates an audio response with custom mime type', function (): void {
    $response = Response::audio('raw-bytes', 'audio/mp3');

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and($response->content()->toArray()['mimeType'])->toBe('audio/mp3');
});

it('creates image response with content meta', function (): void {
    $response = Response::image('raw-bytes')->withMeta(['source' => 'camera']);

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and($response->content()->toArray())->toHaveKey('_meta')
        ->and($response->content()->toArray()['_meta'])->toEqual(['source' => 'camera']);
});

it('creates audio response with content meta', function (): void {
    $response = Response::audio('raw-bytes')->withMeta(['duration' => '3.5s']);

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and($response->content()->toArray())->toHaveKey('_meta')
        ->and($response->content()->toArray()['_meta'])->toEqual(['duration' => '3.5s']);
});

it('can convert response to assistant role', function (): void {
    $response = Response::text('Original message');
    $assistantResponse = $response->asAssistant();

    expect($assistantResponse->content())->toBeInstanceOf(Text::class)
        ->and($assistantResponse->role())->toBe(Role::Assistant)
        ->and($assistantResponse->isError())->toBeFalse();
});

it('preserves error state when converting to assistant role', function (): void {
    $response = Response::error('Error message');
    $assistantResponse = $response->asAssistant();

    expect($assistantResponse->content())->toBeInstanceOf(Text::class)
        ->and($assistantResponse->role())->toBe(Role::Assistant)
        ->and($assistantResponse->isError())->toBeTrue();
});

it('creates a json response', function (): void {
    $data = ['key' => 'value', 'number' => 123];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);

    $content = $response->content();
    expect((string) $content)->toBe(json_encode($data, JSON_THROW_ON_ERROR));
});

it('handles nested arrays in json response', function (): void {
    $data = [
        'user' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'roles' => ['admin', 'developer'],
        ],
        'active' => true,
    ];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class);

    $content = $response->content();
    expect((string) $content)->toBe(json_encode($data, JSON_THROW_ON_ERROR));
});

it('throws JsonException for invalid json data', function (): void {
    $data = ['invalid' => INF];

    expect(function () use ($data): void {
        Response::json($data);
    })->toThrow(JsonException::class);
});

it('handles empty array in json response', function (): void {
    $data = [];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class);

    $content = $response->content();
    expect((string) $content)->toBe(json_encode($data, JSON_THROW_ON_ERROR));
});

it('creates text response with content meta', function (): void {
    $response = Response::text('Hello')->withMeta(['author' => 'John']);

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->content()->toArray())->toHaveKey('_meta')
        ->and($response->content()->toArray()['_meta'])->toEqual(['author' => 'John']);
});

it('creates blob response with content meta', function (): void {
    $response = Response::blob('binary')->withMeta(['encoding' => 'utf-8']);

    expect($response->content())->toBeInstanceOf(Blob::class)
        ->and($response->content()->toArray())->toHaveKey('_meta')
        ->and($response->content()->toArray()['_meta'])->toEqual(['encoding' => 'utf-8']);
});

it('creates a notification response with content meta', function (): void {
    $response = Response::notification('test/event', ['data' => 'value'])->withMeta(['author' => 'system']);

    expect($response->content())->toBeInstanceOf(Notification::class)
        ->and($response->content()->toArray()['params'])->toHaveKey('_meta')
        ->and($response->content()->toArray()['params']['_meta'])->toEqual(['author' => 'system']);
});

it('throws exception when array contains a non-Response object', function (): void {
    expect(fn (): ResponseFactory => Response::make([
        Response::text('Valid'),
        'Invalid string',
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('throws exception when array contains nested ResponseFactory', function (): void {
    $nestedFactory = Response::make(Response::text('Nested'));

    expect(fn (): ResponseFactory => Response::make([
        Response::text('First'),
        $nestedFactory,
        Response::text('Third'),
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('throws exception when an array contains null', function (): void {
    expect(fn (): ResponseFactory => Response::make([
        Response::text('Valid'),
        null,
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('creates compact json response', function (): void {
    $data = ['key' => 'value', 'number' => 123];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class);

    $content = (string) $response->content();
    expect($content)->toBe('{"key":"value","number":123}')
        ->and($content)->not->toContain("\n");
});
