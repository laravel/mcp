<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;
use Tests\Fixtures\ExampleServer;

it('passes the host parameters when the oauth and mcp routes share a domain', function (): void {
    Route::domain('{tenant}.example.com')->group(function (): void {
        app(Registrar::class)->oauthRoutes();
        Mcp::web('tenant-mcp', ExampleServer::class)->middleware(Authenticate::class);
    });

    $this->postJson('http://acme.example.com/tenant-mcp', [])
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="http://acme.example.com/.well-known/oauth-protected-resource/tenant-mcp"');
});

it('resolves the host parameters of the oauth routes from the request host', function (): void {
    Route::domain('{account}.example.com')->group(fn () => app(Registrar::class)->oauthRoutes());

    Mcp::web('shared-mcp', ExampleServer::class)->middleware(Authenticate::class);

    $this->postJson('http://acme.example.com/shared-mcp', [])
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="http://acme.example.com/.well-known/oauth-protected-resource/shared-mcp"');
});

it('resolves the host parameters when the oauth and mcp routes name them differently', function (): void {
    Route::domain('{account}.example.com')->group(fn () => app(Registrar::class)->oauthRoutes());
    Route::domain('{tenant}.example.com')->group(fn () => Mcp::web('tenant-mcp', ExampleServer::class)->middleware(Authenticate::class));

    $this->postJson('http://acme.example.com/tenant-mcp', [])
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="http://acme.example.com/.well-known/oauth-protected-resource/tenant-mcp"');
});

it('does not add the host parameters of the mcp route to the query string', function (): void {
    app(Registrar::class)->oauthRoutes();

    Route::domain('{tenant}.example.com')->group(fn () => Mcp::web('tenant-mcp', ExampleServer::class)->middleware(Authenticate::class));

    $this->postJson('http://acme.example.com/tenant-mcp', [])
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="http://acme.example.com/.well-known/oauth-protected-resource/tenant-mcp"');
});

it('does not add the host parameters of the mcp route when the oauth routes use a fixed domain', function (): void {
    Route::domain('auth.example.com')->group(fn () => app(Registrar::class)->oauthRoutes());
    Route::domain('{tenant}.example.com')->group(fn () => Mcp::web('tenant-mcp', ExampleServer::class)->middleware(Authenticate::class));

    $this->postJson('http://acme.example.com/tenant-mcp', [])
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="http://auth.example.com/.well-known/oauth-protected-resource/tenant-mcp"');
});
