<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Content\Audio;
use Laravel\Mcp\Server\Content\Image;
use League\Flysystem\UnableToReadFile;

it('creates image response from storage', function (): void {
    Storage::fake();
    Storage::put('photos/avatar.png', 'raw-image-data');

    $response = Response::fromStorage('photos/avatar.png');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and((string) $response->content())->toBe('raw-image-data');
});

it('creates image response from specific disk', function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/avatar.png', 'raw-s3-data');

    $response = Response::fromStorage('photos/avatar.png', disk: 's3');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and((string) $response->content())->toBe('raw-s3-data');
});

it('auto-detects mime type for images', function (): void {
    Storage::fake();
    Storage::put('photos/avatar.jpg', 'raw-jpeg-data');

    $response = Response::fromStorage('photos/avatar.jpg');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and($response->content()->toArray()['mimeType'])->toBe(Storage::mimeType('photos/avatar.jpg'));
});

it('allows explicit mime type override', function (): void {
    Storage::fake();
    Storage::put('photos/avatar.png', 'raw-image-data');

    $response = Response::fromStorage('photos/avatar.png', mimeType: 'image/webp');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and($response->content()->toArray()['mimeType'])->toBe('image/webp');
});

it('creates audio response from storage', function (): void {
    Storage::fake();
    Storage::put('recordings/clip.wav', 'raw-audio-data');

    $response = Response::fromStorage('recordings/clip.wav');

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and((string) $response->content())->toBe('raw-audio-data');
});

it('creates audio response from specific disk', function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->put('recordings/clip.wav', 'raw-s3-audio');

    $response = Response::fromStorage('recordings/clip.wav', disk: 's3');

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and((string) $response->content())->toBe('raw-s3-audio');
});

it('auto-detects mime type for audio', function (): void {
    Storage::fake();
    Storage::put('recordings/clip.mp3', 'raw-mp3-data');

    $response = Response::fromStorage('recordings/clip.mp3');

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and($response->content()->toArray()['mimeType'])->toBe(Storage::mimeType('recordings/clip.mp3'));
});

it('throws for unsupported mime types', function (): void {
    Storage::fake();
    Storage::put('docs/report.pdf', 'raw-pdf-data');

    Response::fromStorage('docs/report.pdf');
})->throws(InvalidArgumentException::class, 'Unsupported MIME type [application/pdf] for [docs/report.pdf].');

it('throws when file does not exist', function (): void {
    Storage::fake();

    Response::fromStorage('nonexistent/file.png');
})->throws(InvalidArgumentException::class, 'File not found at path [nonexistent/file.png].');

it('throws when storage disk throws on missing file', function (): void {
    $storage = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
    $storage->shouldReceive('get')->with('missing/file.png')
        ->andThrow(UnableToReadFile::fromLocation('missing/file.png'));

    Storage::shouldReceive('disk')->with(null)->andReturn($storage);

    Response::fromStorage('missing/file.png');
})->throws(InvalidArgumentException::class, 'File not found at path [missing/file.png].');

it('throws when mime type cannot be determined', function (): void {
    $storage = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
    $storage->shouldReceive('get')->with('data/unknown')->andReturn('some-data');
    $storage->shouldReceive('mimeType')->with('data/unknown')->andReturn(false);

    Storage::shouldReceive('disk')->with(null)->andReturn($storage);

    Response::fromStorage('data/unknown');
})->throws(InvalidArgumentException::class, 'Unable to determine MIME type for [data/unknown].');
