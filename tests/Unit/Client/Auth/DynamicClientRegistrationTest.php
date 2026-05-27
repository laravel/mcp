<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\Auth\DynamicClientRegistration;
use Laravel\Mcp\Exceptions\OAuthException;

it('POSTs RFC 7591 metadata and parses the registration response', function (): void {
    Http::fake([
        'https://auth.example.com/register' => Http::response(json_encode([
            'client_id' => 'cid-dyn-1',
            'client_id_issued_at' => 1_700_000_000,
        ]), 201, ['Content-Type' => 'application/json']),
    ]);

    config(['app.name' => 'Test App']);

    $registration = (new DynamicClientRegistration)->register('https://auth.example.com/register', [
        'redirect_uris' => ['https://app.example.com/mcp/notion/callback'],
        'scope' => 'mcp:read',
        'public_client' => true,
    ]);

    expect($registration->clientId)->toBe('cid-dyn-1')
        ->and($registration->clientSecret)->toBeNull();

    Http::assertSent(function ($request): bool {
        if ($request->method() !== 'POST') {
            return false;
        }

        $body = json_decode((string) $request->body(), true);

        return $body['client_name'] === 'Test App'
            && $body['redirect_uris'] === ['https://app.example.com/mcp/notion/callback']
            && $body['grant_types'] === ['authorization_code', 'refresh_token']
            && $body['response_types'] === ['code']
            && $body['token_endpoint_auth_method'] === 'none'
            && $body['scope'] === 'mcp:read';
    });
});

it('omits the scope parameter when scope is null', function (): void {
    Http::fake([
        '*' => Http::response(json_encode(['client_id' => 'cid']), 201, ['Content-Type' => 'application/json']),
    ]);

    (new DynamicClientRegistration)->register('https://auth.example.com/register', [
        'redirect_uris' => ['https://app.example.com/cb'],
        'scope' => null,
        'public_client' => true,
    ]);

    Http::assertSent(function ($request): bool {
        $body = json_decode((string) $request->body(), true);

        return ! array_key_exists('scope', $body);
    });
});

it('uses client_secret_basic when public_client is false', function (): void {
    Http::fake([
        '*' => Http::response(json_encode(['client_id' => 'cid', 'client_secret' => 'sec']), 201, ['Content-Type' => 'application/json']),
    ]);

    (new DynamicClientRegistration)->register('https://auth.example.com/register', [
        'redirect_uris' => ['https://app.example.com/cb'],
        'public_client' => false,
    ]);

    Http::assertSent(function ($request): bool {
        $body = json_decode((string) $request->body(), true);

        return $body['token_endpoint_auth_method'] === 'client_secret_basic';
    });
});

it('captures client_secret and timestamps when the AS issues them', function (): void {
    Http::fake([
        '*' => Http::response(json_encode([
            'client_id' => 'cid-2',
            'client_secret' => 'sec-2',
            'client_id_issued_at' => 1_700_000_000,
            'client_secret_expires_at' => 1_700_010_000,
        ]), 201, ['Content-Type' => 'application/json']),
    ]);

    $registration = (new DynamicClientRegistration)->register('https://auth.example.com/register', [
        'redirect_uris' => ['https://app.example.com/cb'],
        'public_client' => false,
    ]);

    expect($registration->clientId)->toBe('cid-2')
        ->and($registration->clientSecret)->toBe('sec-2')
        ->and($registration->clientIdIssuedAt)->toBe(1_700_000_000)
        ->and($registration->clientSecretExpiresAt)->toBe(1_700_010_000);
});

it('throws OAuthException when the AS responds non-2xx', function (): void {
    Http::fake([
        '*' => Http::response('{"error":"invalid_request"}', 400, ['Content-Type' => 'application/json']),
    ]);

    expect(fn (): mixed => (new DynamicClientRegistration)->register('https://auth.example.com/register', [
        'redirect_uris' => ['https://app.example.com/cb'],
        'public_client' => true,
    ]))->toThrow(OAuthException::class, 'HTTP [400]');
});

it('throws OAuthException when the response body is not valid JSON', function (): void {
    Http::fake([
        '*' => Http::response('not-json', 201, ['Content-Type' => 'application/json']),
    ]);

    expect(fn (): mixed => (new DynamicClientRegistration)->register('https://auth.example.com/register', [
        'redirect_uris' => ['https://app.example.com/cb'],
        'public_client' => true,
    ]))->toThrow(OAuthException::class, 'not valid JSON');
});

it('throws OAuthException when the response is missing client_id', function (): void {
    Http::fake([
        '*' => Http::response(json_encode(['issuer' => 'foo']), 201, ['Content-Type' => 'application/json']),
    ]);

    expect(fn (): mixed => (new DynamicClientRegistration)->register('https://auth.example.com/register', [
        'redirect_uris' => ['https://app.example.com/cb'],
        'public_client' => true,
    ]))->toThrow(OAuthException::class, 'client_id');
});
