<?php

declare(strict_types=1);

use Laravel\Mcp\Client\Auth\WwwAuthenticateChallenge;

it('returns null for missing or empty headers', function (?string $header): void {
    expect(WwwAuthenticateChallenge::parse($header))->toBeNull();
})->with([null, '', '   ']);

it('returns null when the auth scheme is not Bearer', function (): void {
    expect(WwwAuthenticateChallenge::parse('Basic realm="api"'))->toBeNull();
});

it('parses the realm, scope, resource_metadata, error and error_description', function (): void {
    $header = 'Bearer realm="MCP", scope="mcp:read mcp:write", resource_metadata="https://mcp.example.com/.well-known/oauth-protected-resource", error="insufficient_scope", error_description="needs mcp:write"';

    $challenge = WwwAuthenticateChallenge::parse($header);

    expect($challenge)->not->toBeNull()
        ->and($challenge->realm)->toBe('MCP')
        ->and($challenge->scope)->toBe('mcp:read mcp:write')
        ->and($challenge->resourceMetadata)->toBe('https://mcp.example.com/.well-known/oauth-protected-resource')
        ->and($challenge->error)->toBe('insufficient_scope')
        ->and($challenge->errorDescription)->toBe('needs mcp:write')
        ->and($challenge->isInsufficientScope())->toBeTrue();
});

it('parses unquoted params and returns null for missing fields', function (): void {
    $challenge = WwwAuthenticateChallenge::parse('Bearer realm=MCP');

    expect($challenge)->not->toBeNull()
        ->and($challenge->realm)->toBe('MCP')
        ->and($challenge->scope)->toBeNull()
        ->and($challenge->resourceMetadata)->toBeNull()
        ->and($challenge->isInsufficientScope())->toBeFalse();
});
