<?php

declare(strict_types=1);

use Laravel\Mcp\Client\Auth\AuthServerMetadata;

it('reports PKCE S256 support based on the advertised methods', function (): void {
    $supported = new AuthServerMetadata('https://auth.example.com', 'https://auth.example.com/token', codeChallengeMethodsSupported: ['plain', 'S256']);
    $unsupported = new AuthServerMetadata('https://auth.example.com', 'https://auth.example.com/token', codeChallengeMethodsSupported: ['plain']);

    expect($supported->supportsPkceS256())->toBeTrue()
        ->and($unsupported->supportsPkceS256())->toBeFalse();
});

it('reports dynamic registration support based on the registration endpoint', function (): void {
    $supported = new AuthServerMetadata('https://auth.example.com', 'https://auth.example.com/token', registrationEndpoint: 'https://auth.example.com/register');
    $unsupported = new AuthServerMetadata('https://auth.example.com', 'https://auth.example.com/token');

    expect($supported->supportsDynamicRegistration())->toBeTrue()
        ->and($unsupported->supportsDynamicRegistration())->toBeFalse();
});
