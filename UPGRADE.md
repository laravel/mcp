# Upgrade Guide

## Upgrading To 1.0 From 0.9

## Updating Dependencies

**Likelihood Of Impact: High**

In your application's `composer.json` file, update the `laravel/mcp` dependency:

```json
"laravel/mcp": "^1.0"
```

## Protocol Version And The `initialize` Handshake

**Likelihood Of Impact: High**

The server now supports only protocol revision `2026-07-28`. The protocol versions `2025-11-25`, `2025-06-18`, `2025-03-26`, and `2024-11-05` are no longer accepted.

The `initialize` handshake has been replaced by the `server/discover` method. Without a handshake, every request must include the protocol version and client capabilities in its own `params._meta`:

```
// Before...
--> {"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"my-client","version":"1.0.0"}}}
--> {"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}

// After...
--> {"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}
<-- {"jsonrpc":"2.0","id":1,"result":{"supportedVersions":["2026-07-28"],"capabilities":{...},"instructions":null}}
--> {"jsonrpc":"2.0","id":2,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}
```

If your application only defines `Tool`, `Resource`, `Prompt`, and `Server` classes, no changes are required as long as your MCP client supports the `2026-07-28` revision. Both `Laravel\Mcp\Client` and the MCP Inspector support this revision.

Clients that support only the `initialize` flow can no longer connect and will receive a `-32601` error. Re-registering `initialize` via `addMethod()` will not restore compatibility because every request must still pass the protocol metadata check.

## MCP HTTP Headers

**Likelihood Of Impact: High**

The new `ValidateMcpHeaders` middleware is applied to every route registered via `Mcp::web(...)`. Each POST request must include `MCP-Protocol-Version` and `Mcp-Method` headers whose values match the request body. Requests for `tools/call`, `prompts/get`, and `resources/read` must also include an `Mcp-Name` header matching `params.name` or, for `resources/read`, `params.uri`.

This requirement applies to every HTTP client that calls the endpoint, including your application's tests:

```php
// Before...
$this->postJson('mcp-endpoint', [
    'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
    'params' => ['name' => 'say-hi', 'arguments' => []],
]);

// After...
$message = [
    'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
    'params' => [
        'name' => 'say-hi',
        'arguments' => [],
        '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => [],
        ],
    ],
];

$this->postJson('mcp-endpoint', $message, [
    'MCP-Protocol-Version' => '2026-07-28',
    'Mcp-Method' => 'tools/call',
    'Mcp-Name' => 'say-hi',
]);
```

A mismatch returns an HTTP 400 response containing the JSON-RPC error code `-32020`. Requests to the removed `initialize` method are exempt from header validation, but still receive a `-32601` method-not-found error. The `Mcp-Name` header may be encoded as `=?base64?<b64>?=` when a name contains characters that are not valid in a raw header value.

## Session IDs

**Likelihood Of Impact: Medium**

The `Request::sessionId()` and `Request::setSessionId()` methods have been removed, along with the `MCP-Session-Id` header. Every HTTP request and stdio message is now processed independently:

```php
// Before...
public function handle(Request $request): Response
{
    $sessionId = $request->sessionId();

    Cache::put("session:{$sessionId}:last-seen", now());
}
```

There is no replacement. If you need to correlate calls, pass your own identifier through the request arguments or `_meta`.

## The `SessionInitialized` Event

**Likelihood Of Impact: Medium**

The `Laravel\Mcp\Events\SessionInitialized` event and the `initialize` handshake that dispatched it have been removed. There is no replacement event, so you should remove any listeners that reference it:

```php
// Before...
Event::listen(SessionInitialized::class, function (SessionInitialized $event): void {
    Log::info("MCP client connected: {$event->clientName()}");
});
```

## Error Codes

**Likelihood Of Impact: Medium**

A `resources/read` request for a URI that cannot be resolved now returns `-32602` (Invalid params) instead of `-32002`. The new `Laravel\Mcp\Enums\ErrorCode` enum also defines `-32020` for a header mismatch and `-32022` for an unsupported protocol version. Unsupported protocol versions were previously reported as `-32602`:

```php
// Before...
match ($e->getCode()) {
    -32002 => /* resource URI could not be resolved */,
    -32602 => /* unsupported protocol version or invalid params */,
};

// After...
use Laravel\Mcp\Enums\ErrorCode;

match (ErrorCode::tryFrom($e->getCode())) {
    ErrorCode::INVALID_PARAMS => /* invalid params or unresolvable resource URI */,
    ErrorCode::UNSUPPORTED_PROTOCOL_VERSION => /* unsupported protocol version */,
    ErrorCode::HEADER_MISMATCH => /* an MCP header did not match the request body */,
};
```

This change only affects code that inspects error codes numerically.

## PKCE Is Now Required

**Likelihood Of Impact: Medium**

The `OAuthClient::redirect()` method now throws an `OAuthException` when the authorization server's metadata omits `code_challenge_methods_supported`. Previously, only servers that advertised the field without `S256` were rejected; servers that omitted it entirely could proceed without guaranteeing PKCE support:

```php
// Before... redirects without guaranteed PKCE support:
return Mcp::client('github')->oAuthClient()->redirect();

// After... throws an OAuthException before redirecting.
```

There is no opt-out. For servers you control, publish `"code_challenge_methods_supported": ["S256"]` in the `/.well-known/oauth-authorization-server` document. For third-party servers, use a pre-issued `client_id` and `client_secret` with `clientCredentials()` if the client credentials grant is appropriate for your integration.

## Client ID Metadata Documents

**Likelihood Of Impact: Medium**

MCP 2026-07-28 deprecates Dynamic Client Registration in favor of Client ID Metadata Documents. With this mechanism, the `client_id` is an HTTPS URL that points to a JSON document describing your client.

The client determines its `client_id` in the following order: the `clientId` passed to `withOAuth()`, your application's metadata document when the server advertises `client_id_metadata_document_supported`, and finally dynamic registration.

When a metadata document is used, your application is treated as a public client. Therefore, the `client_id` passed to your callback is a URL and there is no client secret:

```php
Mcp::oAuthRoutesFor('github', function (string $client, TokenSet $token) {
    // Before...
    // $token->clientId     === 'a1b2c3d4-...'
    // $token->clientSecret === 'sk_live_...'

    // After...
    // $token->clientId     === 'https://acme.com/mcp/oauth/github/client-metadata.json'
    // $token->clientSecret === null

    Auth::user()->update([
        'mcp_client_id' => $token->clientId,
        'mcp_client_secret' => $token->clientSecret,
    ]);
});
```

Any database column that stores `clientSecret` must be nullable. Code that passes the secret to `refreshCredentials()` must also accept `null`; in that case, the token request uses a `token_endpoint_auth_method` of `none`.

This change also fixes a bug that caused every call to `redirect()` to perform a fresh dynamic registration, creating a new client record on the authorization server for each connection.

## The Client Metadata Route

**Likelihood Of Impact: Low**

In addition to the connect and callback routes, `Mcp::oAuthRoutesFor()` now registers a client ID metadata document at `GET /mcp/oauth/{client}/client-metadata.json`. The route is named `mcp.oauth.{client}.client-metadata`. The supplied `middleware` is not applied to this route because the authorization server must be able to fetch it without authentication.

You may customize the document or move it to another path:

```php
Mcp::oAuthRoutesFor('github', $handler, clientMetadataUri: 'oauth/github/metadata.json', clientMetadata: [
    'client_name' => 'Acme Dashboard',
    'logo_uri' => 'https://acme.com/logo.png',
]);
```

The `client_id` and default `redirect_uris` values are computed from your `APP_URL` and route table, while `token_endpoint_auth_method` is always `none`; these values may not be overridden. Any additional `redirect_uris` are included alongside the generated callback URL. The `client_secret`, `client_secret_expires_at`, and `registration_access_token` keys are stripped from the document.

You should check for route collisions at this path and ensure `APP_URL` is correct in production because the document is built from that value rather than from the incoming request.

## The `ping` Method

**Likelihood Of Impact: Low**

The `ping` method has been removed in favor of `server/discover`. Requests to `ping` now return a `-32601` error. Update any health checks or tooling that send a bare `ping` request.

## The MCP Apps Capability

**Likelihood Of Impact: Low**

The `Server::CAPABILITY_UI` constant has been removed. The capability is no longer advertised as a top-level `io.modelcontextprotocol/ui` boolean and is instead nested under `capabilities.extensions`:

```php
// Before...
// { "tools": {...}, "io.modelcontextprotocol/ui": true }
if (! $this->hasCapability(Server::CAPABILITY_UI)) {
    $this->addCapability(Server::CAPABILITY_UI);
}

// After...
// { "tools": {...}, "extensions": { "io.modelcontextprotocol/ui": {} } }
use Laravel\Mcp\Enums\Extension;

class MyServer extends Server
{
    protected array $extensions = [Extension::Ui];
}
```

MCP Apps behavior is otherwise unchanged. Extending `AppResource` still advertises the capability automatically.

## Custom Client Transports

**Likelihood Of Impact: Low**

The `Laravel\Mcp\Client\Contracts\Transport` contract no longer declares a `setProtocolVersion()` method. Protocol version handling has moved to the `Laravel\Mcp\Client\Contracts\UsesProtocol` interface:

```php
// Before...
class MyTransport implements Transport
{
    public function setProtocolVersion(string $version): void
    {
        $this->version = $version;
    }
}

// After...
use Laravel\Mcp\Client\Contracts\UsesProtocol;
use Laravel\Mcp\Enums\ProtocolVersion;

class MyTransport implements Transport, UsesProtocol
{
    public function useProtocol(ProtocolVersion $protocolVersion): void
    {
        $this->version = $protocolVersion;
    }
}
```

Applications using the bundled `HttpTransport` and `StdioTransport` need no changes.

## New Client Exceptions

**Likelihood Of Impact: Low**

Two exceptions have been added under `Laravel\Mcp\Client\Exceptions`: `TransportException`, which extends `ClientException`, and `TimeoutException`, which extends `TransportException`. A stdio timeout now throws a `TimeoutException`. Some HTTP transport failures now throw a `TransportException`.

Both still extend `ClientException`, so existing broad catch blocks continue to work:

```php
try {
    $client->connect();
} catch (\Laravel\Mcp\Client\Exceptions\TimeoutException $e) {
    // Retry...
} catch (\Laravel\Mcp\Exceptions\ClientException $e) {
    // Unchanged...
}
```

The `JsonRpcException` class is a sibling of `ClientException`, not a child, so structured server errors still require a separate catch block.
