<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use Laravel\Mcp\Icon;

it('accepts https URLs', function (): void {
    $icon = new Icon('https://example.com/icon.png', mimeType: 'image/png', sizes: ['48x48']);

    expect($icon->src)->toBe('https://example.com/icon.png')
        ->and($icon->mimeType)->toBe('image/png')
        ->and($icon->sizes)->toBe(['48x48'])
        ->and($icon->theme)->toBeNull();
});

it('accepts data URIs', function (): void {
    $icon = new Icon('data:image/png;base64,iVBORw0KGgo=');

    expect($icon->src)->toBe('data:image/png;base64,iVBORw0KGgo=');
});

it('rejects unsafe schemes', function (string $unsafeSrc): void {
    new Icon($unsafeSrc);
})
    ->throws(InvalidArgumentException::class, 'Icon src must use https: or data: scheme')
    ->with([
        'http://example.com/icon.png',
        'javascript:alert(1)',
        'file:///etc/passwd',
        'ftp://example.com/icon.png',
        'ws://example.com/icon.png',
        '/relative/path.png',
    ]);

it('rejects invalid themes', function (): void {
    new Icon('https://example.com/icon.png', theme: 'auto');
})->throws(InvalidArgumentException::class, "Icon theme must be 'light' or 'dark'");

it('accepts light and dark themes', function (string $theme): void {
    $icon = new Icon('https://example.com/icon.png', theme: $theme);
    expect($icon->theme)->toBe($theme);
})->with(['light', 'dark']);

it('omits null and empty fields in toArray', function (): void {
    $icon = new Icon('https://example.com/icon.png');

    expect($icon->toArray())->toBe(['src' => 'https://example.com/icon.png']);
});

it('emits all set fields in toArray', function (): void {
    $icon = new Icon(
        src: 'https://example.com/icon.svg',
        mimeType: 'image/svg+xml',
        sizes: ['any'],
        theme: 'dark',
    );

    expect($icon->toArray())->toBe([
        'src' => 'https://example.com/icon.svg',
        'mimeType' => 'image/svg+xml',
        'sizes' => ['any'],
        'theme' => 'dark',
    ]);
});

it('builds a data URI from a file via fromFile', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'icon-test-');
    file_put_contents($path, "\x89PNG\r\n\x1a\n");

    try {
        $icon = Icon::fromFile($path, sizes: ['48x48']);

        expect($icon->src)->toStartWith('data:')
            ->and($icon->src)->toContain(';base64,')
            ->and($icon->mimeType)->toBeString()
            ->and($icon->sizes)->toBe(['48x48']);
    } finally {
        @unlink($path);
    }
});

it('throws when fromFile cannot read the file', function (): void {
    Icon::fromFile('/nonexistent/path/icon.png');
})->throws(RuntimeException::class, 'Icon file not found or not readable');

it('exposes asset() helper for public-path icons', function (): void {
    config(['app.url' => 'https://app.test']);
    URL::forceRootUrl('https://app.test');
    URL::forceScheme('https');

    $icon = Icon::asset('icons/server.png', mimeType: 'image/png');

    expect($icon->src)->toBe('https://app.test/icons/server.png')
        ->and($icon->mimeType)->toBe('image/png');
});
