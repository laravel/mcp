<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server\Ui\Enums\Permission;
use Laravel\Mcp\Server\Ui\Permissions;

it('serializes as empty when no permissions set', function (): void {
    expect(Permissions::make()->toArray())->toEqual([]);
});

it('serializes camera permission as empty object', function (): void {
    $permissions = Permissions::make()->camera();

    $array = $permissions->toArray();

    expect($array)->toHaveKey('camera')
        ->and(json_encode($array['camera']))->toBe('{}');
});

it('serializes multiple permissions', function (): void {
    $permissions = Permissions::make()
        ->camera()
        ->microphone()
        ->geolocation()
        ->clipboardWrite();

    $array = $permissions->toArray();

    expect($array)->toHaveKey('camera')
        ->toHaveKey('microphone')
        ->toHaveKey('geolocation')
        ->toHaveKey('clipboardWrite');
});

it('supports constructor parameters', function (): void {
    $permissions = new Permissions(camera: true, clipboardWrite: true);

    $array = $permissions->toArray();

    expect($array)->toHaveKey('camera')
        ->toHaveKey('clipboardWrite')
        ->not->toHaveKey('microphone')
        ->not->toHaveKey('geolocation');
});

it('implements Arrayable', function (): void {
    expect(new Permissions)->toBeInstanceOf(Arrayable::class);
});

it('allows permissions via enum', function (): void {
    $permissions = Permissions::make()->allow(
        Permission::Camera,
        Permission::ClipboardWrite,
    );

    $array = $permissions->toArray();

    expect($array)->toHaveKey('camera')
        ->toHaveKey('clipboardWrite')
        ->not->toHaveKey('microphone')
        ->not->toHaveKey('geolocation');
});
