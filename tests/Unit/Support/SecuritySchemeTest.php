<?php

use Laravel\Mcp\Support\SecurityScheme;

it('returns an oauth scheme', function (): void {
    $scheme = SecurityScheme::oauth2('read', 'write')
        ->with('flows', [
            'authorizationCode' => [
                'authorizationUrl' => 'https://example.com/oauth/authorize',
                'tokenUrl' => 'https://example.com/oauth/token',
            ],
        ]);

    expect($scheme->toArray())->toBe([
        'type' => 'oauth2',
        'flows' => [
            'authorizationCode' => [
                'authorizationUrl' => 'https://example.com/oauth/authorize',
                'tokenUrl' => 'https://example.com/oauth/token',
            ],
        ],
        'scopes' => ['read', 'write'],
    ]);
});

it('can set scopes', function (): void {
    $scheme = SecurityScheme::oauth2()
        ->scopes('read', 'write', 'delete');

    expect($scheme->toArray()['scopes'])->toBe(['read', 'write', 'delete']);
});

it('can set an oauth tyoe', function (): void {
    $scheme = SecurityScheme::oauth2();

    expect($scheme->toArray()['type'])->toBe('oauth2');
});

it('can set a noauth type', function (): void {
    $scheme = SecurityScheme::noauth();

    expect($scheme)->toBe([
        'type' => 'noauth',
    ]);
});

it('can set a type', function (): void {
    $scheme = SecurityScheme::type('apiKey');

    expect($scheme->toArray()['type'])->toBe('apiKey');
});

it('can set an apiKey auth', function (): void {
    $scheme = SecurityScheme::apiKey('X-API-KEY', 'header');

    expect($scheme)->toBe([
        'type' => 'apiKey',
        'name' => 'X-API-KEY',
        'in' => 'header',
    ]);
});

it('can set a bearer auth', function (): void {
    $scheme = SecurityScheme::bearer('JWT');

    expect($scheme)->toBe([
        'type' => 'http',
        'scheme' => 'bearer',
        'bearerFormat' => 'JWT',
    ]);
});

it('can set a basic auth', function (): void {
    $scheme = SecurityScheme::basic();

    expect($scheme)->toBe([
        'type' => 'http',
        'scheme' => 'basic',
    ]);
});

it('can make a set of schemes', function (): void {
    $schemes = SecurityScheme::make([
        SecurityScheme::basic(),
        SecurityScheme::bearer('JWT'),
        [
            'type' => 'apiKey',
            'name' => 'X-API-KEY',
            'in' => 'header',
        ],
    ]);

    expect($schemes)->toBe([
        [
            'type' => 'http',
            'scheme' => 'basic',
        ],
        [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ],
        [
            'type' => 'apiKey',
            'name' => 'X-API-KEY',
            'in' => 'header',
        ],
    ]);
});
