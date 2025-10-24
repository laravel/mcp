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
                public function createAuthorizationCodeGrantClient($name, $redirectUris, $confidential, $user, $enableDeviceFlow) {
                    return (object) [
                        "id" => "test-client-id",
                        "grantTypes" => ["authorization_code"],
                        "redirectUris" => $redirectUris,
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
                public function createAuthorizationCodeGrantClient($name, $redirectUris, $confidential, $user, $enableDeviceFlow) {
                    return (object) [
                        "id" => "test-client-id",
                        "grantTypes" => ["authorization_code"],
                        "redirectUris" => $redirectUris,
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

it('handles oauth registration with incorrect redirect domain', function (): void {
    if (! class_exists('Laravel\Passport\ClientRepository')) {
        // Create a mock ClientRepository class for testing
        eval('
            namespace Laravel\Passport;
            class ClientRepository {
                public function createAuthorizationCodeGrantClient($name, $redirectUris, $confidential, $user, $enableDeviceFlow) {
                    return (object) [
                        "id" => "test-client-id",
                        "grantTypes" => ["authorization_code"],
                        "redirectUris" => $redirectUris,
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
