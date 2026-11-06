<?php

declare(strict_types=1);

use Laravel\Mcp\Server\OAuth\RedirectUriPolicy;

it('accepts http and https urls', function (string $uri): void {
    expect((new RedirectUriPolicy)->isValid($uri))->toBeTrue();
})->with([
    'https' => 'https://example.com/callback',
    'http localhost with port' => 'http://localhost:8080/callback',
    'http loopback' => 'http://127.0.0.1/callback',
]);

it('rejects malformed urls and urls with userinfo', function (string $uri): void {
    expect((new RedirectUriPolicy(['*'], ['cursor']))->isValid($uri))->toBeFalse();
})->with([
    'no scheme' => 'example.com/callback',
    'user' => 'https://user@example.com/callback',
    'user and password' => 'https://user:secret@example.com/callback',
    'custom scheme with userinfo' => 'cursor://user@callback',
    'not a url' => 'https://',
]);

it('only accepts configured custom schemes with a host', function (): void {
    $policy = new RedirectUriPolicy([], ['cursor']);

    expect($policy->isValid('cursor://anysphere.cursor-retrieval/oauth/callback'))->toBeTrue()
        ->and($policy->isValid('vscode://callback'))->toBeFalse()
        ->and($policy->isValid('cursor:///callback'))->toBeFalse();
});

it('permits every domain with a wildcard', function (): void {
    expect((new RedirectUriPolicy(['*']))->isPermitted('https://anything.example/callback'))->toBeTrue();
});

it('permits custom scheme uris regardless of the redirect domains', function (): void {
    expect((new RedirectUriPolicy(['https://example.com'], ['cursor']))->isPermitted('cursor://callback'))->toBeTrue();
});

it('permits uris under an allowed domain prefix', function (string $domain): void {
    $policy = new RedirectUriPolicy([$domain]);

    expect($policy->isPermitted('https://example.com/callback'))->toBeTrue()
        ->and($policy->isPermitted('https://example.com.evil.test/callback'))->toBeFalse()
        ->and($policy->isPermitted('https://other.example/callback'))->toBeFalse();
})->with([
    'without trailing slash' => 'https://example.com',
    'with trailing slash' => 'https://example.com/',
]);

it('permits any localhost port when a localhost domain is allowed', function (string $domain, string $uri): void {
    expect((new RedirectUriPolicy([$domain]))->isPermitted($uri))->toBeTrue();
})->with([
    ['http://localhost', 'http://localhost:51234/callback'],
    ['http://127.0.0.1', 'http://[::1]:51234/callback'],
    ['http://[::1]/', 'http://127.0.0.1:51234/callback'],
]);

it('does not permit https localhost through a localhost domain', function (): void {
    expect((new RedirectUriPolicy(['http://localhost']))->isPermitted('https://localhost:8443/callback'))->toBeFalse();
});

it('detects plain http loopback uris', function (string $uri, bool $expected): void {
    expect((new RedirectUriPolicy)->isLocalhost($uri))->toBe($expected);
})->with([
    ['http://localhost/callback', true],
    ['http://localhost:3000/callback', true],
    ['http://127.0.0.1:3000/callback', true],
    ['http://[::1]:3000/callback', true],
    ['https://localhost/callback', false],
    ['http://user@localhost/callback', false],
    ['http://localhost.example.com/callback', false],
    ['http:///nohost', false],
]);

it('reads redirect domains and custom schemes from configuration', function (): void {
    config()->set('mcp.redirect_domains', ['https://example.com']);
    config()->set('mcp.custom_schemes', ['cursor']);

    $policy = RedirectUriPolicy::fromConfig();

    expect($policy->allowedDomains())->toBe(['https://example.com/'])
        ->and($policy->isValid('cursor://callback'))->toBeTrue()
        ->and($policy->isPermitted('https://other.example/callback'))->toBeFalse();
});
