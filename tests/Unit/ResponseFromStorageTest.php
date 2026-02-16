<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Content\Audio;
use Laravel\Mcp\Server\Content\Image;

it('creates image response from storage', function (): void {
    Storage::fake();
    Storage::put('photos/avatar.png', 'raw-image-data');

    $response = Response::imageFromStorage('photos/avatar.png');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and((string) $response->content())->toBe('raw-image-data');
});

it('creates image response from specific disk', function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/avatar.png', 'raw-s3-data');

    $response = Response::imageFromStorage('photos/avatar.png', disk: 's3');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and((string) $response->content())->toBe('raw-s3-data');
});

it('auto-detects mime type from storage for images', function (): void {
    Storage::fake();
    Storage::put('photos/avatar.jpg', 'raw-jpeg-data');

    $response = Response::imageFromStorage('photos/avatar.jpg');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and($response->content()->toArray()['mimeType'])->toBe(Storage::mimeType('photos/avatar.jpg'));
});

it('allows explicit mime type override for images', function (): void {
    Storage::fake();
    Storage::put('photos/avatar.png', 'raw-image-data');

    $response = Response::imageFromStorage('photos/avatar.png', mimeType: 'image/webp');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and($response->content()->toArray()['mimeType'])->toBe('image/webp');
});

it('creates audio response from storage', function (): void {
    Storage::fake();
    Storage::put('recordings/clip.wav', 'raw-audio-data');

    $response = Response::audioFromStorage('recordings/clip.wav');

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and((string) $response->content())->toBe('raw-audio-data');
});

it('creates audio response from specific disk', function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->put('recordings/clip.wav', 'raw-s3-audio');

    $response = Response::audioFromStorage('recordings/clip.wav', disk: 's3');

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and((string) $response->content())->toBe('raw-s3-audio');
});

it('auto-detects mime type from storage for audio', function (): void {
    Storage::fake();
    Storage::put('recordings/clip.mp3', 'raw-mp3-data');

    $response = Response::audioFromStorage('recordings/clip.mp3');

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and($response->content()->toArray()['mimeType'])->toBe(Storage::mimeType('recordings/clip.mp3'));
});

it('throws when image file does not exist in storage', function (): void {
    Storage::fake();

    Response::imageFromStorage('nonexistent/file.png');
})->throws(InvalidArgumentException::class, 'File not found at path [nonexistent/file.png].');

it('throws when audio file does not exist in storage', function (): void {
    Storage::fake();

    Response::audioFromStorage('nonexistent/file.wav');
})->throws(InvalidArgumentException::class, 'File not found at path [nonexistent/file.wav].');
