<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;
use Tests\Fixtures\ExampleServer;

it('registers mcp scope during boot', function (): void {
    if (! class_exists(Passport::class)) {
        require_once __DIR__.'/../../Fixtures/PassportPassport.php';
    }

    Passport::$scopes = ['custom' => 'Custom scope'];

    $provider = new McpServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $this->app->boot();

    expect(Passport::$scopes)->toHaveKey('mcp:use');
    expect(Passport::$scopes['custom'])->toBe('Custom scope');
});

it('sorts the www-authenticate challenge outside authentication middleware', function (): void {
    // AddWwwAuthenticateHeader decorates a returned 401, so it has to run
    // outside whatever produces one. Laravel sorts Authenticate to the front of
    // the stack because it implements AuthenticatesRequests, which is in the
    // framework's middleware priority list. Without an entry of its own this
    // middleware sorts *after* auth on any protected route, never sees the
    // rejection, and the challenge is silently absent on exactly the
    // OAuth-protected servers it exists for.
    $registrar = new Registrar;

    $route = $registrar->web('/priority/mcp', ExampleServer::class)->middleware('auth');

    $resolved = app('router')->gatherRouteMiddleware($route);

    $challenge = array_search(AddWwwAuthenticateHeader::class, $resolved, true);
    $authenticate = array_search(Authenticate::class.':', $resolved, true);

    if ($authenticate === false) {
        $authenticate = array_key_first(array_filter(
            $resolved,
            fn (string $middleware): bool => str_starts_with($middleware, Authenticate::class),
        ));
    }

    expect($challenge)->not->toBeFalse('AddWwwAuthenticateHeader is missing from the resolved middleware.');
    expect($authenticate)->not->toBeNull('The auth middleware is missing from the resolved middleware.');
    expect($challenge)->toBeLessThan(
        $authenticate,
        'AddWwwAuthenticateHeader must sort before the auth middleware, or it never sees the 401 it exists to decorate.',
    );
});
