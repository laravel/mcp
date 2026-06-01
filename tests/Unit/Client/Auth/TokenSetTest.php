<?php

declare(strict_types=1);

use Laravel\Mcp\Client\Auth\TokenSet;

it('round-trips through fromArray and toArray', function (): void {
    $set = new TokenSet('access', 'refresh', 1_700_000_000, 'mcp:read mcp:write');

    expect(TokenSet::fromArray($set->toArray()))->toEqual($set);
});

it('converts relative expiry from a token response into absolute timestamps', function (): void {
    $set = TokenSet::fromTokenResponse([
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'expires_in' => 3600,
        'refresh_expires_in' => 7200,
        'scope' => 'mcp:read',
    ], now: 1_700_000_000);

    expect($set->accessToken)->toBe('access')
        ->and($set->refreshToken)->toBe('refresh')
        ->and($set->expiresAt)->toBe(1_700_003_600)
        ->and($set->refreshExpiresAt)->toBe(1_700_007_200)
        ->and($set->scope)->toBe('mcp:read');
});

it('normalizes a token response without expiry or optional fields', function (): void {
    $set = TokenSet::fromTokenResponse([
        'access_token' => 'access',
        'refresh_token' => '',
        'scope' => '',
    ], now: 1_700_000_000);

    expect($set->expiresAt)->toBe(0)
        ->and($set->refreshExpiresAt)->toBeNull()
        ->and($set->refreshToken)->toBeNull()
        ->and($set->scope)->toBeNull();
});

it('round-trips a refresh-token expiry through fromArray and toArray', function (): void {
    $set = new TokenSet('access', 'refresh', 1_700_000_000, 'mcp:read', 1_700_000_500);

    expect(TokenSet::fromArray($set->toArray()))->toEqual($set);
});

it('normalizes empty refresh tokens and scopes to null on fromArray', function (): void {
    $set = TokenSet::fromArray([
        'access_token' => 'access',
        'refresh_token' => '',
        'expires_at' => 0,
        'scope' => '',
    ]);

    expect($set->refreshToken)->toBeNull()
        ->and($set->scope)->toBeNull()
        ->and($set->expiresAt)->toBe(0);
});

it('reports as expired only when expiresAt is reached after the skew', function (): void {
    $expired = new TokenSet('a', null, time() - 5, null);
    $about = new TokenSet('a', null, time() + 10, null);
    $fresh = new TokenSet('a', null, time() + 600, null);

    expect($expired->isExpired())->toBeTrue()
        ->and($about->isExpired())->toBeTrue()
        ->and($fresh->isExpired())->toBeFalse();
});

it('never expires when expiresAt is zero', function (): void {
    $set = new TokenSet('a', null, 0, null);

    expect($set->isExpired())->toBeFalse()
        ->and($set->isExpired(skewSeconds: 99_999))->toBeFalse();
});
