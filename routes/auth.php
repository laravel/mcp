<?php

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as Router;

/**
Issuers:
- https://yourserver.com/admin
- https://yourserver.com/sales
- https://yourserver.com/public

Discovery Endpoints:
- https://yourserver.com/.well-known/oauth-authorization-server/admin
- https://yourserver.com/.well-known/oauth-authorization-server/sales
- https://yourserver.com/.well-known/oauth-authorization-server/public
 */

/**
 * OAuth Authorization Server
 * Server Discovery - https://datatracker.ietf.org/doc/html/rfc8414#section-2
 */
Router::get('/.well-known/oauth-authorization-server', function (Request $request) {
    return response()->json([
        'issuer' => url('/'),
        'authorization_endpoint' => route('passport.authorizations.authorize'),
        'token_endpoint' => route('passport.token'),
        'registration_endpoint' => url('/oauth/register'),
        'response_types_supported' => ['code'],
        'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic'],
        'code_challenge_methods_supported' => ['S256'],
        'grant_types_supported' => $this->grantTypes,
        'scopes_supported' => ['mcp:use', 'mcp:test', 'mcp:extra', 'fourth-wall'],
    ]);
})->name('mcp.oauth.authorization-server');

/**
 * OAuth Authorization Server
 * Dynamic Client Registration - https://datatracker.ietf.org/doc/html/rfc7591
 *
 * Allows the client to register a new client_id to use in the OAuth flow.
 */
Router::post('/oauth/register', function (Request $request) {
    $clients = Container::getInstance()->make(
        "Laravel\Passport\ClientRepository"
    );
    $payload = $request->json()->all();

    $client = $clients->createAuthorizationCodeGrantClient(
        name: $this->clientNamePrefix.$payload['client_name'],
        redirectUris: $payload['redirect_uris'],
        confidential: false,
        user: null,
        enableDeviceFlow: false,
    );

    return response()->json([
        'client_id' => $client->id,
        'grant_types' => $client->grantTypes,
        'response_types' => ['code'],
        'redirect_uris' => $client->redirectUris,
        'scope' => 'mcp:use',
        'token_endpoint_auth_method' => 'none',
    ]);
});
