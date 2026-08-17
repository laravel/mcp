<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\ExampleServer;

class RejectsEveryone
{
    public function handle(Request $request, Closure $next): Response
    {
        return response()->json(['error' => 'unauthorized'], 401);
    }
}

beforeEach(fn () => app(Registrar::class)->oauthRoutes());

it('challenges when authentication is sorted ahead of the route middleware', function (): void {
    Route::middleware(SubstituteBindings::class)->group(function (): void {
        Mcp::web('protected-mcp', ExampleServer::class)->middleware(Authenticate::class);
    });

    $this->postJson('protected-mcp', [])
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/protected-mcp').'"');
});

it('challenges when authentication is not part of the middleware priority list', function (): void {
    Route::middleware(RejectsEveryone::class)->group(function (): void {
        Mcp::web('custom-auth-mcp', ExampleServer::class);
    });

    $this->postJson('custom-auth-mcp', [])
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/custom-auth-mcp').'"');
});

it('challenges when authentication is applied through the auth alias', function (): void {
    config()->set('auth.guards.api', ['driver' => 'session', 'provider' => 'users']);

    Route::middleware(SubstituteBindings::class)->group(function (): void {
        Mcp::web('aliased-mcp', ExampleServer::class)->middleware('auth:api');
    });

    $this->postJson('aliased-mcp', [])
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/aliased-mcp').'"');
});

it('does not challenge on routes that are not mcp servers', function (): void {
    Route::middleware(Authenticate::class)->post('not-mcp', fn () => response()->json([]));

    $this->postJson('not-mcp', [])
        ->assertStatus(401)
        ->assertHeaderMissing('WWW-Authenticate');
});

it('does not challenge when the middleware is excluded from the route', function (): void {
    Route::middleware(Authenticate::class)->group(function (): void {
        Mcp::web('opted-out-mcp', ExampleServer::class)->withoutMiddleware(AddWwwAuthenticateHeader::class);
    });

    $this->postJson('opted-out-mcp', [])
        ->assertStatus(401)
        ->assertHeaderMissing('WWW-Authenticate');
});
