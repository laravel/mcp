<?php

declare(strict_types=1);

use Laravel\Mcp\Client\Auth\ProtectedResourceMetadata;
use Laravel\Mcp\Exceptions\OAuthException;

it('returns the first authorization server', function (): void {
    $metadata = new ProtectedResourceMetadata('https://mcp.example.com/mcp', ['https://auth.example.com', 'https://auth2.example.com']);

    expect($metadata->primaryAuthorizationServer())->toBe('https://auth.example.com');
});

it('throws an OAuthException when no authorization servers are listed', function (): void {
    $metadata = new ProtectedResourceMetadata('https://mcp.example.com/mcp', []);

    expect(fn (): string => $metadata->primaryAuthorizationServer())
        ->toThrow(OAuthException::class, 'lists no authorization servers');
});
