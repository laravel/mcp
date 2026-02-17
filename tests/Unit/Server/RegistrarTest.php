<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Registrar;
use Tests\Fixtures\ExampleServer;

it('registers a local server and retrieves it', function (): void {
    $registrar = new Registrar;

    $registrar->local('test-server', ExampleServer::class);

    $server = $registrar->getLocalServer('test-server');

    expect($server)->toBeCallable();
});

it('returns null for non-existent local server', function (): void {
    $registrar = new Registrar;

    $server = $registrar->getLocalServer('non-existent');

    expect($server)->toBeNull();
});

it('registers a web server and retrieves it', function (): void {
    $registrar = new Registrar;

    $route = $registrar->web('/api/mcp', ExampleServer::class);

    $webServer = $registrar->getWebServer('api/mcp');

    expect($webServer)->toBeInstanceOf(\Illuminate\Routing\Route::class);
    expect($webServer)->toBe($route);
});

it('returns null for non-existent web server', function (): void {
    $registrar = new Registrar;

    $server = $registrar->getWebServer('non-existent');

    expect($server)->toBeNull();
});

it('returns all registered servers', function (): void {
    $registrar = new Registrar;

    $registrar->local('local-server', ExampleServer::class);
    $registrar->web('/web/mcp', ExampleServer::class);

    $servers = $registrar->servers();

    expect($servers)->toHaveCount(2);
    expect($servers)->toHaveKey('local-server');
    expect($servers)->toHaveKey('web/mcp');
});

it('registers oauth routes', function (): void {
    $registrar = new Registrar;

    $registrar->oauthRoutes();

    // Get the registered routes
    $routes = Route::getRoutes();
    $hasProtectedResource = false;
    $hasAuthServer = false;

    foreach ($routes as $route) {
        if ($route->getName() === 'mcp.oauth.protected-resource') {
            $hasProtectedResource = true;
        }

        if ($route->getName() === 'mcp.oauth.authorization-server') {
            $hasAuthServer = true;
        }
    }

    expect($hasProtectedResource)->toBeTrue();
    expect($hasAuthServer)->toBeTrue();
});

it('registers oauth routes with custom prefix', function (): void {
    $registrar = new Registrar;

    $registrar->oauthRoutes('custom-oauth');

    // Get the registered routes
    $routes = Route::getRoutes();
    $hasProtectedResource = false;
    $hasAuthServer = false;
    $hasRegisterRoute = false;

    foreach ($routes as $route) {
        if ($route->getName() === 'mcp.oauth.protected-resource') {
            $hasProtectedResource = true;
        }

        if ($route->getName() === 'mcp.oauth.authorization-server') {
            $hasAuthServer = true;
        }

        if ($route->uri() === 'custom-oauth/register') {
            $hasRegisterRoute = true;
        }
    }

    expect($hasProtectedResource)->toBeTrue();
    expect($hasAuthServer)->toBeTrue();
    expect($hasRegisterRoute)->toBeTrue();
});

it('adds mcp scope when passport is available', function (): void {
    // Mock Passport class existence
    if (! class_exists('Laravel\Passport\Passport')) {
        // Create a mock Passport class for testing
        eval('
            namespace Laravel\Passport;
            class Passport {
                public static $scopes = [];
                public static function tokensCan($scopes) {
                    self::$scopes = $scopes;
                }
            }
        ');
    }

    $registrar = new Registrar;

    // Clear any existing scopes
    \Laravel\Passport\Passport::$scopes = [];

    $registrar->oauthRoutes();

    expect(\Laravel\Passport\Passport::$scopes)->toHaveKey('mcp:use');
    expect(\Laravel\Passport\Passport::$scopes['mcp:use'])->toBe('Use MCP server');
});

it('does not duplicate mcp scope if already exists', function (): void {
    if (! class_exists('Laravel\Passport\Passport')) {
        eval('
            namespace Laravel\Passport;
            class Passport {
                public static $scopes = [];
                public static function tokensCan($scopes) {
                    self::$scopes = $scopes;
                }
            }
        ');
    }

    $registrar = new Registrar;

    // Set existing scope
    \Laravel\Passport\Passport::$scopes = ['mcp:use' => 'Existing MCP scope'];

    $registrar->oauthRoutes();

    // Should not overwrite existing scope
    expect(\Laravel\Passport\Passport::$scopes['mcp:use'])->toBe('Existing MCP scope');
});

it('handles oauth registration endpoint', function (): void {
    if (! class_exists('Laravel\Passport\ClientRepository')) {
        // Create a mock ClientRepository class for testing
        eval('
            namespace Laravel\Passport;
            class ClientRepository {
                public function createAuthorizationCodeGrantClient(string $name, array $redirectUris, bool $confidential = true, $user = null, bool $enableDeviceFlow = false) {
                    return (object) [
                        "id" => "test-client-id",
                        "grant_types" => ["authorization_code"],
                        "redirect_uris" => $redirectUris,
                    ];
                }
            }
        ');
    }

    $registrar = new Registrar;
    $registrar->oauthRoutes();

    $this->app->instance('Laravel\Passport\ClientRepository', new \Laravel\Passport\ClientRepository);

    $response = $this->postJson('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => ['http://localhost:3000/callback'],
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'client_id' => 'test-client-id',
        'grant_types' => ['authorization_code'],
        'response_types' => ['code'],
        'redirect_uris' => ['http://localhost:3000/callback'],
        'scope' => 'mcp:use',
        'token_endpoint_auth_method' => 'none',
    ]);
});

it('handles oauth registration with allowed domains', function (): void {
    if (! class_exists('Laravel\Passport\ClientRepository')) {
        // Create a mock ClientRepository class for testing
        eval('
            namespace Laravel\Passport;
            class ClientRepository {
                public function createAuthorizationCodeGrantClient(string $name, array $redirectUris, bool $confidential = true, $user = null, bool $enableDeviceFlow = false) {
                    return (object) [
                        "id" => "test-client-id",
                        "grant_types" => ["authorization_code"],
                        "redirect_uris" => $redirectUris,
                    ];
                }
            }
        ');
    }

    $registrar = new Registrar;
    $registrar->oauthRoutes();

    config()->set('mcp.redirect_domains', ['http://localhost:3000/']);

    $this->app->instance('Laravel\Passport\ClientRepository', new \Laravel\Passport\ClientRepository);

    $response = $this->postJson('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => ['http://localhost:3000/callback'],
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'client_id' => 'test-client-id',
        'grant_types' => ['authorization_code'],
        'response_types' => ['code'],
        'redirect_uris' => ['http://localhost:3000/callback'],
        'scope' => 'mcp:use',
        'token_endpoint_auth_method' => 'none',
    ]);
});

it('allows localhost with dynamic port when allow_localhost_dynamic_port is enabled', function (string $uri): void {
    if (! class_exists('Laravel\Passport\ClientRepository')) {
        eval('
            namespace Laravel\Passport;
            class ClientRepository {
                public function createAuthorizationCodeGrantClient(string $name, array $redirectUris, bool $confidential = true, $user = null, bool $enableDeviceFlow = false) {
                    return (object) [
                        "id" => "test-client-id",
                        "grant_types" => ["authorization_code"],
                        "redirect_uris" => $redirectUris,
                    ];
                }
            }
        ');
    }

    $registrar = new Registrar;
    $registrar->oauthRoutes();

    config()->set('mcp.redirect_domains', ['https://example.com']);
    config()->set('mcp.allow_localhost_dynamic_port', true);

    $this->app->instance('Laravel\Passport\ClientRepository', new \Laravel\Passport\ClientRepository);

    $response = $this->postJson('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => [$uri],
    ]);

    $response->assertStatus(200);
})->with([
    'localhost' => ['http://localhost:18293/callback'],
    '127.0.0.1' => ['http://127.0.0.1:29100/callback'],
    'IPv6 loopback' => ['http://[::1]:39201/callback'],
]);

it('rejects localhost with dynamic port when allow_localhost_dynamic_port is disabled', function (string $uri): void {
    if (! class_exists('Laravel\Passport\ClientRepository')) {
        eval('
            namespace Laravel\Passport;
            class ClientRepository {
                public function createAuthorizationCodeGrantClient(string $name, array $redirectUris, bool $confidential = true, $user = null, bool $enableDeviceFlow = false) {
                    return (object) [
                        "id" => "test-client-id",
                        "grant_types" => ["authorization_code"],
                        "redirect_uris" => $redirectUris,
                    ];
                }
            }
        ');
    }

    $registrar = new Registrar;
    $registrar->oauthRoutes();

    config()->set('mcp.redirect_domains', ['https://example.com']);
    config()->set('mcp.allow_localhost_dynamic_port', false);

    $this->app->instance('Laravel\Passport\ClientRepository', new \Laravel\Passport\ClientRepository);

    $response = $this->postJson('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => [$uri],
    ]);

    $response->assertStatus(422);
})->with([
    'localhost' => ['http://localhost:18293/callback'],
    '127.0.0.1' => ['http://127.0.0.1:29100/callback'],
    'IPv6 loopback' => ['http://[::1]:39201/callback'],
]);

it('does not allow non-localhost URLs via allow_localhost_dynamic_port', function (): void {
    if (! class_exists('Laravel\Passport\ClientRepository')) {
        eval('
            namespace Laravel\Passport;
            class ClientRepository {
                public function createAuthorizationCodeGrantClient(string $name, array $redirectUris, bool $confidential = true, $user = null, bool $enableDeviceFlow = false) {
                    return (object) [
                        "id" => "test-client-id",
                        "grant_types" => ["authorization_code"],
                        "redirect_uris" => $redirectUris,
                    ];
                }
            }
        ');
    }

    $registrar = new Registrar;
    $registrar->oauthRoutes();

    config()->set('mcp.redirect_domains', ['https://example.com']);
    config()->set('mcp.allow_localhost_dynamic_port', true);

    $this->app->instance('Laravel\Passport\ClientRepository', new \Laravel\Passport\ClientRepository);

    $response = $this->postJson('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => ['http://evil.com:18293/callback'],
    ]);

    $response->assertStatus(422);
});

it('does not allow https localhost URLs via allow_localhost_dynamic_port', function (): void {
    if (! class_exists('Laravel\Passport\ClientRepository')) {
        eval('
            namespace Laravel\Passport;
            class ClientRepository {
                public function createAuthorizationCodeGrantClient(string $name, array $redirectUris, bool $confidential = true, $user = null, bool $enableDeviceFlow = false) {
                    return (object) [
                        "id" => "test-client-id",
                        "grant_types" => ["authorization_code"],
                        "redirect_uris" => $redirectUris,
                    ];
                }
            }
        ');
    }

    $registrar = new Registrar;
    $registrar->oauthRoutes();

    config()->set('mcp.redirect_domains', ['https://example.com']);
    config()->set('mcp.allow_localhost_dynamic_port', true);

    $this->app->instance('Laravel\Passport\ClientRepository', new \Laravel\Passport\ClientRepository);

    $response = $this->postJson('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => ['https://localhost:18293/callback'],
    ]);

    $response->assertStatus(422);
});

it('handles oauth registration with incorrect redirect domain', function (): void {
    if (! class_exists('Laravel\Passport\ClientRepository')) {
        // Create a mock ClientRepository class for testing
        eval('
            namespace Laravel\Passport;
            class ClientRepository {
                public function createAuthorizationCodeGrantClient(string $name, array $redirectUris, bool $confidential = true, $user = null, bool $enableDeviceFlow = false) {
                    return (object) [
                        "id" => "test-client-id",
                        "grant_types" => ["authorization_code"],
                        "redirect_uris" => $redirectUris,
                    ];
                }
            }
        ');
    }

    $registrar = new Registrar;
    $registrar->oauthRoutes();

    config()->set('mcp.redirect_domains', ['http://allowed-domain.com/']);

    $this->app->instance('Laravel\Passport\ClientRepository', new \Laravel\Passport\ClientRepository);

    $response = $this->postJson('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => ['http://not-allowed.com/callback'],
    ]);

    $response->assertStatus(422);
});

it('handles oauth discovery with multi-segment paths', function (): void {
    // Fake Passport's routes so the oauthRoutes() helper can resolve them.
    Route::get('/oauth/authorize')->name('passport.authorizations.authorize');
    Route::post('/oauth/token')->name('passport.token');

    $registrar = new Registrar;
    $registrar->oauthRoutes();

    // Test protected resource endpoint with multi-segment path
    $response = $this->getJson('/.well-known/oauth-protected-resource/mcp/weather');

    $response->assertStatus(200);
    $response->assertJson([
        'resource' => url('/mcp/weather'),
        'authorization_servers' => [url('/mcp/weather')],
        'scopes_supported' => ['mcp:use'],
    ]);

    // Test authorization server endpoint with multi-segment path
    $response = $this->getJson('/.well-known/oauth-authorization-server/mcp/weather');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'issuer',
        'authorization_endpoint',
        'token_endpoint',
        'registration_endpoint',
        'response_types_supported',
        'code_challenge_methods_supported',
        'scopes_supported',
        'grant_types_supported',
    ]);
    $response->assertJson([
        'issuer' => url('/mcp/weather'),
        'scopes_supported' => ['mcp:use'],
        'response_types_supported' => ['code'],
        'code_challenge_methods_supported' => ['S256'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
    ]);
});

it('handles oauth discovery with single segment paths', function (): void {
    // Fake Passport's routes so the oauthRoutes() helper can resolve them.
    Route::get('/oauth/authorize')->name('passport.authorizations.authorize');
    Route::post('/oauth/token')->name('passport.token');

    $registrar = new Registrar;
    $registrar->oauthRoutes();

    // Test backward compatibility with single-segment paths
    $response = $this->getJson('/.well-known/oauth-protected-resource/mcp');

    $response->assertStatus(200);
    $response->assertJson([
        'resource' => url('/mcp'),
        'authorization_servers' => [url('/mcp')],
        'scopes_supported' => ['mcp:use'],
    ]);

    $response = $this->getJson('/.well-known/oauth-authorization-server/mcp');

    $response->assertStatus(200);
    $response->assertJson([
        'issuer' => url('/mcp'),
    ]);
});

it('handles oauth discovery with no path', function (): void {
    // Fake Passport's routes so the oauthRoutes() helper can resolve them.
    Route::get('/oauth/authorize')->name('passport.authorizations.authorize');
    Route::post('/oauth/token')->name('passport.token');

    $registrar = new Registrar;
    $registrar->oauthRoutes();

    // Test with no path (root)
    $response = $this->getJson('/.well-known/oauth-protected-resource');

    $response->assertStatus(200);
    $response->assertJson([
        'resource' => url('/'),
        'authorization_servers' => [url('/')],
        'scopes_supported' => ['mcp:use'],
    ]);

    $response = $this->getJson('/.well-known/oauth-authorization-server');

    $response->assertStatus(200);
    $response->assertJson([
        'issuer' => url('/'),
    ]);
});
