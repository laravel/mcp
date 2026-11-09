<?php

declare(strict_types=1);

use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\ValidateOrigin;
use Tests\Fixtures\ExampleServer;

it('accepts a request without an origin header', function (): void {
    $message = listToolsMessage();

    $this->postJson('test-mcp', $message, mcpHeaders($message))->assertStatus(200);
});

it('accepts a request from an allowed origin', function (string $origin): void {
    config(['app.url' => 'https://app.test/']);

    $message = listToolsMessage();

    $this->postJson('test-mcp', $message, [...mcpHeaders($message), 'Origin' => $origin])->assertStatus(200);
})->with([
    'application url' => 'https://app.test',
    'application url with default port' => 'https://APP.test:443',
    'localhost' => 'http://localhost:6274',
    'ipv4 loopback' => 'http://127.0.0.1:8000',
    'ipv6 loopback' => 'http://[::1]:3000',
]);

it('rejects a request from an origin that is not allowed', function (string $origin): void {
    config(['app.url' => 'https://app.test']);

    $message = listToolsMessage();

    $response = $this->postJson('test-mcp', $message, [...mcpHeaders($message), 'Origin' => $origin]);

    $response->assertStatus(403);

    expect($response->json())->toEqual([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32600,
            'message' => 'Forbidden: The request origin is not allowed.',
        ],
    ]);
})->with([
    'foreign host' => 'https://evil.example',
    'subdomain of application' => 'https://evil.app.test',
    'different scheme' => 'http://app.test',
    'different port' => 'https://app.test:8443',
    'opaque origin' => 'null',
    'unsupported scheme' => 'file://app.test',
]);

it('rejects a rebinding request whose host matches its origin', function (): void {
    config(['app.url' => 'https://app.test']);

    $message = listToolsMessage();

    $this->postJson('http://evil.example/test-mcp', $message, [...mcpHeaders($message), 'Origin' => 'http://evil.example'])
        ->assertStatus(403);
});

it('accepts a request from a configured origin', function (): void {
    config(['mcp.allowed_origins' => ['https://client.example/']]);

    $message = listToolsMessage();

    $this->postJson('test-mcp', $message, [...mcpHeaders($message), 'Origin' => 'https://client.example'])->assertStatus(200);
    $this->postJson('test-mcp', $message, [...mcpHeaders($message), 'Origin' => 'https://evil.example'])->assertStatus(403);
});

it('accepts any origin when all origins are allowed', function (): void {
    config(['mcp.allowed_origins' => ['*']]);

    $message = listToolsMessage();

    $this->postJson('test-mcp', $message, [...mcpHeaders($message), 'Origin' => 'https://evil.example'])->assertStatus(200);
});

it('skips validation when the middleware is removed from the route', function (): void {
    Mcp::web('open-mcp', ExampleServer::class)->withoutMiddleware(ValidateOrigin::class);

    $message = listToolsMessage();

    $this->postJson('open-mcp', $message, [...mcpHeaders($message), 'Origin' => 'https://evil.example'])->assertStatus(200);
});

it('validates the origin on get and delete requests', function (string $method): void {
    $this->json($method, 'test-mcp', [], ['Origin' => 'https://evil.example'])->assertStatus(403);
    $this->json($method, 'test-mcp')->assertStatus(405);
})->with(['GET', 'DELETE']);
