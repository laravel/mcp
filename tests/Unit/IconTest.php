<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use Laravel\Mcp\Enums\IconTheme;
use Laravel\Mcp\Schema\Icon;

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

it('accepts light and dark themes', function (IconTheme $theme): void {
    $icon = new Icon('https://example.com/icon.png', theme: $theme);
    expect($icon->theme)->toBe($theme);
})->with([IconTheme::Light, IconTheme::Dark]);

it('omits null and empty fields in toArray', function (): void {
    $icon = new Icon('https://example.com/icon.png');

    expect($icon->toArray())->toBe(['src' => 'https://example.com/icon.png']);
});

it('emits all set fields in toArray', function (): void {
    $icon = new Icon(
        src: 'https://example.com/icon.svg',
        mimeType: 'image/svg+xml',
        sizes: ['any'],
        theme: IconTheme::Dark,
    );

    expect($icon->toArray())->toBe([
        'src' => 'https://example.com/icon.svg',
        'mimeType' => 'image/svg+xml',
        'sizes' => ['any'],
        'theme' => 'dark',
    ]);
});

it('resolves Icon attribute relative paths via asset()', function (): void {
    config(['app.url' => 'https://app.test']);
    URL::forceRootUrl('https://app.test');
    URL::forceScheme('https');

    $attribute = new Laravel\Mcp\Server\Attributes\Icon('icons/server.png', mimeType: 'image/png');

    expect($attribute->toIcon()->src)->toBe('https://app.test/icons/server.png')
        ->and($attribute->toIcon()->mimeType)->toBe('image/png');
});

it('passes Icon attribute https URLs through unchanged', function (): void {
    $attribute = new Laravel\Mcp\Server\Attributes\Icon('https://example.com/icon.png');

    expect($attribute->toIcon()->src)->toBe('https://example.com/icon.png');
});
