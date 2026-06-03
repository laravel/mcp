<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\Transport\HttpTransport;

it('resolves a closure token at request time', function (): void {
    Http::fake([
        'https://mcp.test/mcp' => Http::response(
            json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['ok' => true]]),
            200,
            ['Content-Type' => 'application/json'],
        ),
    ]);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->withToken(fn (): string => 'dynamic-token');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping', 'params' => []]));

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer dynamic-token'));
});

it('throws AuthorizationRequiredException on a 401 with a challenge', function (): void {
    Http::fake([
        'https://mcp.test/mcp' => Http::response('', 401, [
            'WWW-Authenticate' => 'Bearer error="invalid_token", resource_metadata="https://mcp.test/.well-known/oauth-protected-resource/mcp", scope="files:read"',
        ]),
    ]);

    $transport = new HttpTransport('https://mcp.test/mcp');

    try {
        $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => []]));
        $this->fail('Expected AuthorizationRequiredException.');
    } catch (AuthorizationRequiredException $authorizationRequiredException) {
        expect($authorizationRequiredException->resourceMetadataUrl())
            ->toBe('https://mcp.test/.well-known/oauth-protected-resource/mcp')
            ->and($authorizationRequiredException->challenge?->error)->toBe('invalid_token')
            ->and($authorizationRequiredException->scope())->toBe('files:read');
    }
});

it('throws AuthorizationRequiredException on a 403', function (): void {
    Http::fake([
        'https://mcp.test/mcp' => Http::response('', 403),
    ]);

    $transport = new HttpTransport('https://mcp.test/mcp');

    expect(fn () => $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => []])))
        ->toThrow(AuthorizationRequiredException::class);
});

it('exposes the configured endpoint url', function (): void {
    expect((new HttpTransport('https://mcp.test/mcp'))->url())->toBe('https://mcp.test/mcp');
});
