<?php

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server\Ui\Csp;
use Laravel\Mcp\Server\Ui\Permissions;
use Laravel\Mcp\Server\Ui\AppMeta;

it('serializes as empty when no fields set', function (): void {
    $meta = new AppMeta;

    expect($meta->toArray())->toEqual(['prefersBorder' => true]);
});

it('serializes all fields when provided', function (): void {
    $meta = new AppMeta(
        csp: new Csp(
            connectDomains: ['https://api.example.com'],
            resourceDomains: ['https://cdn.example.com'],
        ),
        permissions: new Permissions(camera: true, clipboardWrite: true),
        domain: 'a904794854a047f6.claudemcpcontent.com',
        prefersBorder: false,
    );

    $array = $meta->toArray();

    expect($array['csp'])->toEqual([
        'connectDomains' => ['https://api.example.com'],
        'resourceDomains' => ['https://cdn.example.com'],
    ])
        ->and($array['permissions'])->toHaveKey('clipboardWrite')
        ->and($array['permissions'])->toHaveKey('camera')
        ->and($array['domain'])->toBe('a904794854a047f6.claudemcpcontent.com')
        ->and($array['prefersBorder'])->toBeFalse();
});

it('omits null values', function (): void {
    $meta = new AppMeta(
        prefersBorder: true,
    );

    $array = $meta->toArray();

    expect($array)->toHaveKey('prefersBorder')
        ->not->toHaveKey('csp')
        ->not->toHaveKey('permissions')
        ->not->toHaveKey('domain');
});

it('implements Arrayable', function (): void {
    $meta = new AppMeta;

    expect($meta)->toBeInstanceOf(Arrayable::class);
});

it('supports fluent builder via make()', function (): void {
    $meta = AppMeta::make()
        ->csp(Csp::make()->connectDomains(['https://api.example.com']))
        ->permissions(Permissions::make()->clipboardWrite())
        ->domain('sandbox.example.com')
        ->prefersBorder(false);

    $array = $meta->toArray();

    expect($array['csp'])->toEqual(['connectDomains' => ['https://api.example.com']])
        ->and($array['permissions'])->toHaveKey('clipboardWrite')
        ->and($array['domain'])->toBe('sandbox.example.com')
        ->and($array['prefersBorder'])->toBeFalse();
});

it('omits empty csp and permissions objects', function (): void {
    $meta = AppMeta::make()
        ->csp(Csp::make())
        ->permissions(Permissions::make());

    expect($meta->toArray())->toEqual(['prefersBorder' => true]);
});
