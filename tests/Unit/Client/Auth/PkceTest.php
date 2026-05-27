<?php

declare(strict_types=1);

use Laravel\Mcp\Client\Auth\Pkce;

it('generates a verifier and matching S256 challenge', function (): void {
    $pkce = Pkce::generate();

    expect($pkce->method)->toBe('S256')
        ->and(strlen($pkce->verifier))->toBeGreaterThanOrEqual(43)
        ->and(strlen($pkce->verifier))->toBeLessThanOrEqual(128)
        ->and($pkce->verifier)->toMatch('/^[A-Za-z0-9\-_]+$/')
        ->and($pkce->challenge)->toMatch('/^[A-Za-z0-9\-_]+$/');

    $expected = rtrim(strtr(base64_encode(hash('sha256', $pkce->verifier, true)), '+/', '-_'), '=');
    expect($pkce->challenge)->toBe($expected);
});

it('produces a different verifier and challenge on each generate', function (): void {
    $a = Pkce::generate();
    $b = Pkce::generate();

    expect($a->verifier)->not->toBe($b->verifier);
    expect($a->challenge)->not->toBe($b->challenge);
});
