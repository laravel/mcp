<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\OAuth\TokenSet;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\WebClient;
use Tests\Fixtures\Client\OAuthCallbackController;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
});

function fakeOAuthSession(): array
{
    return ['mcp.oauth.'.sha1('https://mcp.test/mcp') => [
        'state' => 'the-state',
        'verifier' => 'the-verifier',
        'client_id' => 'client-123',
        'client_secret' => null,
        'token_endpoint' => 'https://auth.test/token',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
    ]];
}

function registerGithubClient(): void
{
    Mcp::registerClient('github', fn (): WebClient => Client::web('https://mcp.test/mcp')->withOAuth(
        clientId: 'client-123',
        redirectUri: 'https://app.test/callback',
    ));
}

it('exchanges the code and invokes the handler with the client name', function (): void {
    Http::fake([
        'https://auth.test/token' => Http::response([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]),
    ]);

    registerGithubClient();

    $capturedProvider = null;
    $capturedToken = null;

    Mcp::oAuthRoutesFor('github', function (string $provider, TokenSet $token) use (&$capturedProvider, &$capturedToken): RedirectResponse {
        $capturedProvider = $provider;
        $capturedToken = $token;

        return redirect('/dashboard');
    });

    $this->withSession(fakeOAuthSession())
        ->get('/mcp/oauth/github/callback?code=auth-code&state=the-state')
        ->assertRedirect('/dashboard');

    expect($capturedProvider)->toBe('github')
        ->and($capturedToken)->toBeInstanceOf(TokenSet::class)
        ->and($capturedToken->accessToken)->toBe('access-token')
        ->and($capturedToken->refreshToken)->toBe('refresh-token');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://auth.test/token'
        && ($request['grant_type'] ?? null) === 'authorization_code'
        && ($request['code'] ?? null) === 'auth-code');
});

it('registers a connect route that redirects to the authorization server', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'resource' => 'https://mcp.test/mcp',
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    registerGithubClient();

    Mcp::oAuthRoutesFor('github', fn (string $provider, TokenSet $token): RedirectResponse => redirect('/dashboard'));

    $response = $this->withSession([])->get('/mcp/github/connect');

    $response->assertRedirectContains('https://auth.test/authorize');
});

it('supports controller array syntax for the handler', function (): void {
    Http::fake([
        'https://auth.test/token' => Http::response(['access_token' => 'access-token']),
    ]);

    registerGithubClient();

    Mcp::oAuthRoutesFor('github', [OAuthCallbackController::class, 'callback']);

    $this->withSession(fakeOAuthSession())
        ->get('/mcp/oauth/github/callback?code=auth-code&state=the-state')
        ->assertRedirect('/connected/github');
});

it('applies the web middleware group by default to both routes', function (): void {
    Mcp::oAuthRoutesFor('github', fn (string $provider, TokenSet $token): null => null);

    expect(Route::getRoutes()->getByName('mcp.oauth.github.connect')->gatherMiddleware())->toContain('web')
        ->and(Route::getRoutes()->getByName('mcp.oauth.github.callback')->gatherMiddleware())->toContain('web');
});

it('allows the middleware to be overridden on both routes', function (): void {
    Mcp::oAuthRoutesFor('github', fn (string $provider, TokenSet $token): null => null, middleware: ['web', 'auth']);

    expect(Route::getRoutes()->getByName('mcp.oauth.github.connect')->gatherMiddleware())->toContain('web')->toContain('auth')
        ->and(Route::getRoutes()->getByName('mcp.oauth.github.callback')->gatherMiddleware())->toContain('web')->toContain('auth');
});

it('falls back to redirecting home when the handler returns nothing', function (): void {
    Http::fake([
        'https://auth.test/token' => Http::response(['access_token' => 'access-token']),
    ]);

    registerGithubClient();

    Mcp::oAuthRoutesFor('github', function (string $provider, TokenSet $token): void {
        //
    });

    $this->withSession(fakeOAuthSession())
        ->get('/mcp/oauth/github/callback?code=auth-code&state=the-state')
        ->assertRedirect('/');
});

it('passes the stored return destination to the handler and fallback redirect', function (): void {
    Http::fake([
        'https://auth.test/token' => Http::response(['access_token' => 'access-token']),
    ]);

    registerGithubClient();

    $capturedReturnTo = null;

    Mcp::oAuthRoutesFor('github', function (string $provider, TokenSet $token, ?string $returnTo) use (&$capturedReturnTo): void {
        $capturedReturnTo = $returnTo;
    });

    $session = fakeOAuthSession();
    $session['mcp.oauth.'.sha1('https://mcp.test/mcp')]['return_to'] = '/connected';

    $this->withSession($session)
        ->get('/mcp/oauth/github/callback?code=auth-code&state=the-state')
        ->assertRedirect('/connected');

    expect($capturedReturnTo)->toBe('/connected');
});

it('forwards challenge metadata and scope from the connect route into discovery', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/custom-resource' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    Mcp::registerClient('github', fn (): WebClient => Client::web('https://mcp.test/mcp')->withOAuth(
        clientId: 'client-123',
        redirectUri: 'https://app.test/callback',
    ));

    Mcp::oAuthRoutesFor('github', fn (string $provider, TokenSet $token): null => null);

    $this->withSession([])
        ->get('/mcp/github/connect?resource_metadata=https://mcp.test/.well-known/custom-resource&scope=files:read')
        ->assertRedirectContains('https://auth.test/authorize')
        ->assertRedirectContains('scope=files%3Aread');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://mcp.test/.well-known/custom-resource');
});
