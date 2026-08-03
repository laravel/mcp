<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;
use Tests\Fixtures\ExampleServer;

function registerAuthenticatedMcpRoute(): void
{
    Route::middleware(SubstituteBindings::class)->group(function (): void {
        Mcp::web('protected-mcp', ExampleServer::class)->middleware(Authenticate::class);
    });
}

it('adds the OAuth challenge to the 401 of an authenticated MCP route', function (): void {
    app(Registrar::class)->oauthRoutes();

    registerAuthenticatedMcpRoute();

    $response = $this->postJson('protected-mcp', []);

    $response->assertStatus(401);
    $response->assertHeader(
        'WWW-Authenticate',
        'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/protected-mcp').'"'
    );
});

it('reorders the accept header before authenticating an MCP route', function (): void {
    registerAuthenticatedMcpRoute();

    $response = $this->post('protected-mcp', [], ['Accept' => 'text/event-stream, application/json']);

    $response->assertStatus(401);
    $response->assertJson(['message' => 'Unauthenticated.']);
});
