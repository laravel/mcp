# Upgrade Guide

## Upgrading To 1.0 From 0.9

### Updating Your Dependencies

**Likelihood Of Impact: High**

Update your `laravel/mcp` dependency to `^1.0` in your application's `composer.json` file, then run `composer update`:

```json
"laravel/mcp": "^1.0"
```

Once your dependencies are up to date, work through the changes below. Most applications that only use the documented `Tool`, `Resource`, `Prompt`, and `Server` classes are affected by the first two changes. The rest matter only if you work with the protocol, sessions, or client internals directly.

### The Server Now Speaks Only MCP 2026-07-28, and the `initialize` Handshake Is Gone

**Likelihood Of Impact: High**

The server no longer implements the `initialize` handshake and no longer accepts protocol versions `2025-11-25`, `2025-06-18`, `2025-03-26`, or `2024-11-05`. It understands a single revision, `2026-07-28`, negotiated through a new `server/discover` method. Because there is no more handshake and no more session, every subsequent request must carry the protocol version and client capabilities in its own `params._meta`, not just in a one-time handshake call.

```php
// Before (0.x): one handshake, then a bare session.
// --> {"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"my-client","version":"1.0.0"}}}
// <-- {"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-11-25","capabilities":{...},"serverInfo":{...}}}
// --> {"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}

// After (1.0): "initialize" is rejected outright.
// --> {"jsonrpc":"2.0","id":1,"method":"initialize","params":{...}}
// <-- {"jsonrpc":"2.0","id":1,"error":{"code":-32601,"message":"The method [initialize] was not found."}}

// Discover the server instead, then repeat the version and capabilities on every call:
// --> {"jsonrpc":"2.0","id":1,"method":"server/discover","params":{}}
// <-- {"jsonrpc":"2.0","id":1,"result":{"supportedVersions":["2026-07-28"],"capabilities":{...},"instructions":null}}
// --> {"jsonrpc":"2.0","id":2,"method":"tools/list",
//      "params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28",
//                          "io.modelcontextprotocol/clientCapabilities":{}}}}
```

If you only build `Tool`, `Resource`, `Prompt`, and `Server` classes and let an MCP client talk to your server, you are unaffected as long as that client already speaks the `2026-07-28` discovery handshake. `Laravel\Mcp\Client` and the current MCP Inspector both do. Any client that only knows the old `initialize` flow, such as an older SDK or a hand-rolled JSON-RPC caller, can no longer connect and gets a hard `-32601` with no fallback. There is no supported way to keep serving such a client. Re-registering `initialize` with `addMethod()` is not enough on its own, since every request, including a custom-registered `initialize`, still passes through the server's protocol metadata check, which requires the modern `_meta` block that a legacy client never sends.

### Every HTTP Request Must Now Mirror Its Protocol Version, Method, And Target Name As Headers

**Likelihood Of Impact: High**

A new `ValidateMcpHeaders` middleware is wired unconditionally into every route registered with `Mcp::web(...)`. Each POST must carry an `MCP-Protocol-Version` header and an `Mcp-Method` header that match the request body. For `tools/call`, `prompts/get`, and `resources/read` specifically, it must also carry an `Mcp-Name` header matching the call's `name` (or `uri`, for `resources/read`). This applies to any HTTP client hitting the endpoint, including a hand-rolled test or curl script, not just traffic from a full MCP client.

```php
// Before (0.x): a bare JSON-RPC POST worked.
$this->postJson('mcp-endpoint', [
    'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
    'params' => ['name' => 'say-hi', 'arguments' => []],
]);

// After (1.0): the same request now gets HTTP 400:
// {"jsonrpc":"2.0","id":1,"error":{"code":-32020,"message":"Header mismatch: The [MCP-Protocol-Version] header is required."}}
// Send the mirrored headers instead:
$this->postJson('mcp-endpoint', $message, [
    'MCP-Protocol-Version' => '2026-07-28',
    'Mcp-Method' => 'tools/call',
    'Mcp-Name' => 'say-hi',
]);
```

`initialize` is exempt, so a legacy handshake you add back yourself still works. `Mcp-Name` may be Base64-sentinel-encoded as `=?base64?<b64>?=` for names containing characters that do not survive as a raw header value. The server decodes it before comparing. A mismatch returns HTTP 400 with JSON-RPC error code `-32020`.

### `Request::sessionId()` Is Removed, and Every Request Is Now Stateless

**Likelihood Of Impact: Medium**

`Request::sessionId()` and `Request::setSessionId()` are deleted, along with the `MCP-Session-Id` header and all the session-tracking plumbing behind them. Every HTTP POST and every stdio line is now processed as an independent request. There is no server-side session concept left to query.

```php
// Before (0.x): inside a Tool/Resource/Prompt handle().
public function handle(Request $request): Response
{
    $sessionId = $request->sessionId();
    Cache::put("session:{$sessionId}:last-seen", now());
    // ...
}

// After (1.0): sessionId() no longer exists.
public function handle(Request $request): Response
{
    // There is no equivalent replacement. Thread your own identifier through
    // request arguments or `_meta` if you need to correlate calls.
}
```

If you never called `Request::sessionId()`, this has no effect on you.

### The `SessionInitialized` Event Is Removed

**Likelihood Of Impact: Medium**

`Laravel\Mcp\Events\SessionInitialized` is gone along with the `initialize` handshake it used to fire from at the end of every completed handshake. There is no replacement event.

```php
// Before (0.x)
use Laravel\Mcp\Events\SessionInitialized;

Event::listen(SessionInitialized::class, function (SessionInitialized $event): void {
    Log::info("MCP client connected: {$event->clientName()}");
});

// After (1.0)
// The event class no longer exists. Remove the listener, and log from inside
// your own Tool/Resource/Prompt handlers if you need similar visibility.
```

Search your listeners for `SessionInitialized::class` before upgrading. If nothing references it, there is nothing to do.

### Error Codes Realigned With The MCP 2026-07-28 Spec

**Likelihood Of Impact: Medium**

A `resources/read` request for an unresolvable URI now returns the standard `-32602` (Invalid params) instead of the package's old `-32002`. A new `Laravel\Mcp\Enums\ErrorCode` enum also introduces dedicated codes for protocol-level failures: `-32020` for a mirrored header mismatch (see above), and `-32022` for an unsupported protocol version, which used to be reported as `-32602`.

```php
// Before (0.x)
match ($e->getCode()) {
    -32002 => /* resource URI could not be resolved */,
    -32602 => /* unsupported protocol version during initialize, or invalid params */,
};

// After (1.0)
use Laravel\Mcp\Enums\ErrorCode;

match (ErrorCode::tryFrom($e->getCode())) {
    ErrorCode::INVALID_PARAMS => /* resource URI could not be resolved (was -32002), or invalid params */,
    ErrorCode::UNSUPPORTED_PROTOCOL_VERSION => /* unsupported protocol version (was -32602) */,
    ErrorCode::HEADER_MISMATCH => /* new: a mirrored HTTP header didn't match the request body */,
};
```

This only matters if your code inspects `JsonRpcException::getCode()`, or a raw `error.code`, numerically. Prefer matching on `ErrorCode` going forward.

### The OAuth Client Refuses Authorization Servers That Do Not Advertise PKCE

**Likelihood Of Impact: Medium**

`OAuthClient::redirect()` now throws an `OAuthException` when the authorization server's metadata omits `code_challenge_methods_supported` entirely. Previously only a server that advertised the field *without* `S256` was rejected, so a server that omitted it altogether passed straight through and the flow continued with no PKCE guarantee. The MCP authorization spec is explicit: if `code_challenge_methods_supported` is absent, the authorization server does not support PKCE and clients must refuse to proceed.

```php
// Before (0.x): a server with no code_challenge_methods_supported was accepted.
// {"issuer":"https://auth.example.com","authorization_endpoint":"...","token_endpoint":"..."}
return Mcp::client('github')->oAuthClient()->redirect(); // redirected fine, no PKCE

// After (1.0): the same server is rejected before any redirect happens.
// OAuthException: The authorization server metadata does not advertise
// [code_challenge_methods_supported], so it does not support the required PKCE.
```

There is no opt-out, and that is deliberate. If you hit this against a server you control, publish `"code_challenge_methods_supported": ["S256"]` in its `/.well-known/oauth-authorization-server` document. If you hit it against a third-party server, that server cannot be used safely for an authorization-code flow and you will need a pre-issued `client_id`/`client_secret` with `clientCredentials()` instead. You are unaffected if every server you connect to already advertises `S256`, which includes any server built with this package.

### The OAuth Client Prefers A Client ID Metadata Document Over Dynamic Registration

**Likelihood Of Impact: Medium**

MCP 2026-07-28 deprecates Dynamic Client Registration in favour of Client ID Metadata Documents, where the `client_id` is an HTTPS URL pointing at a JSON document describing your client. When no `client_id` is configured, the client now resolves one in this order: a `clientId` passed to `withOAuth()`, then the metadata document your application publishes if the server advertises `client_id_metadata_document_supported`, then dynamic registration, and only then an exception.

The practical consequence is the shape of what reaches your callback handler. On the metadata-document path your client is a *public* client: the `client_id` handed to you is a URL and there is no client secret at all.

```php
Mcp::oAuthRoutesFor('github', function (string $client, TokenSet $token) {
    // Before (0.x): always a dynamically registered pair.
    // $token->clientId     === 'a1b2c3d4-...'          (opaque, new on every connect)
    // $token->clientSecret === 'sk_live_...'            (a string)

    // After (1.0), when the server supports metadata documents:
    // $token->clientId     === 'https://acme.com/mcp/oauth/github/client-metadata.json'
    // $token->clientSecret === null

    Auth::user()->update([
        'mcp_client_id' => $token->clientId,
        'mcp_client_secret' => $token->clientSecret, // must be nullable
    ]);
});
```

Audit any column you persist `clientSecret` into for a `NOT NULL` constraint, and any code that passes it back to `refreshCredentials()` — a null secret is now expected and the token request is sent with `token_endpoint_auth_method` of `none`. This is also a fix worth having: previously every call to `redirect()` performed a fresh dynamic registration, so connecting three times created three separate client records on the authorization server. You are unaffected if you pass an explicit `clientId` to `withOAuth()`, or if the servers you talk to do not advertise `client_id_metadata_document_supported`, in which case dynamic registration still runs exactly as before.

### `Mcp::oAuthRoutesFor()` Now Registers A Third, Publicly Readable Route

**Likelihood Of Impact: Low**

Alongside the connect and callback routes, `Mcp::oAuthRoutesFor()` publishes the client ID metadata document at `GET /mcp/oauth/{client}/client-metadata.json`, named `mcp.oauth.{client}.client-metadata`. It deliberately does **not** receive the `middleware` argument you pass, because the authorization server has to fetch it unauthenticated; it is served with `Cache-Control: max-age=3600, public`. The document exposes your `APP_NAME` and `APP_URL` as `client_name` and `client_uri`.

```php
// Customise the document, or move it off the default path:
Mcp::oAuthRoutesFor('github', $handler, clientMetadataUri: 'oauth/github/metadata.json', clientMetadata: [
    'client_name' => 'Acme Dashboard',
    'logo_uri' => 'https://acme.com/logo.png',
]);
```

`client_id`, `redirect_uris`, and `token_endpoint_auth_method` are always computed from your `APP_URL` and route table and cannot be overridden — `client_id` must match the document's own URL exactly or the authorization server rejects it. Extra `redirect_uris` you supply are appended to the published callback route rather than replacing it. `client_secret`, `client_secret_expires_at`, and `registration_access_token` are stripped, since a public metadata document must never carry credentials. Check for a route collision if your application already serves that path, and set `APP_URL` correctly in production: the document is built from it, not from the incoming request's host.

### The Server No Longer Answers Bare `ping` Requests

**Likelihood Of Impact: Low**

The `ping` method handler was replaced by `server/discover` and dropped from the server's method map entirely.

```php
// Before (0.x)
// --> {"jsonrpc":"2.0","id":1,"method":"ping"}
// <-- {"jsonrpc":"2.0","id":1,"result":{}}

// After (1.0)
// --> {"jsonrpc":"2.0","id":1,"method":"ping"}
// <-- {"jsonrpc":"2.0","id":1,"error":{"code":-32601,"message":"The method [ping] was not found."}}
```

This only matters if a health check or custom tooling sends a bare `ping` expecting success.

### MCP Apps Capability Moves Under `extensions`, and `Server::CAPABILITY_UI` Is Removed

**Likelihood Of Impact: Low**

MCP Apps itself, meaning `AppResource`, `RendersApp`, and app tools and resources, has not changed. Extending `AppResource` still auto-advertises the capability with zero code changes. What changed is the wire shape: the server used to raise a top-level `io.modelcontextprotocol/ui` boolean via the public `Server::CAPABILITY_UI` constant, and it now nests the same signal under `capabilities.extensions`.

```php
// Before (0.x)
// Capabilities shape: { "tools": {...}, "io.modelcontextprotocol/ui": true }
if (! $this->hasCapability(Server::CAPABILITY_UI)) { // constant no longer exists
    $this->addCapability(Server::CAPABILITY_UI);
}

// After (1.0)
// Capabilities shape: { "tools": {...}, "extensions": { "io.modelcontextprotocol/ui": {} } }
use Laravel\Mcp\Enums\Extension;

class MyServer extends Server
{
    protected array $extensions = [Extension::Ui];
}
```

This only affects you if you referenced `Server::CAPABILITY_UI` or called `addCapability('io.modelcontextprotocol/ui')` directly.

### Custom Client Transports Implement `UsesProtocol` Instead Of `setProtocolVersion()`

**Likelihood Of Impact: Low**

`Laravel\Mcp\Client\Contracts\Transport` no longer declares `setProtocolVersion(string $version): void`. Protocol-version awareness moved to an opt-in `Laravel\Mcp\Client\Contracts\UsesProtocol` interface.

```php
// Before (0.x)
class MyTransport implements Transport
{
    public function setProtocolVersion(string $version): void { $this->version = $version; }
}

// After (1.0)
use Laravel\Mcp\Client\Contracts\UsesProtocol;
use Laravel\Mcp\Enums\ProtocolVersion;

class MyTransport implements Transport, UsesProtocol
{
    public function useProtocol(ProtocolVersion $protocolVersion): void { $this->version = $protocolVersion; }
}
```

This only affects a hand-written `Transport` implementation. Applications using only the bundled `HttpTransport` and `StdioTransport` need no changes.

### New, More Specific Client Exceptions: `TransportException` And `TimeoutException`

**Likelihood Of Impact: Low**

Two new exception classes were added under `Laravel\Mcp\Client\Exceptions`: `TransportException` (extends `ClientException`) and `TimeoutException` (extends `TransportException`). A stdio timeout now throws `TimeoutException` instead of a plain `ClientException`, and some HTTP transport failures throw `TransportException` instead of the generic one. Both still extend `ClientException`, so an existing broad catch keeps working.

```php
// Before (0.x)
try {
    $client->connect();
} catch (\Laravel\Mcp\Exceptions\ClientException $e) {
    // Caught everything.
}

// After (1.0)
// The code above still compiles and behaves exactly the same. You can be
// more specific if you would like to be:
try {
    $client->connect();
} catch (\Laravel\Mcp\Client\Exceptions\TimeoutException $e) {
    // React to a timeout specifically, for example by retrying.
} catch (\Laravel\Mcp\Exceptions\ClientException $e) {
    // Everything else, unchanged.
}
```

Remember that `JsonRpcException` is a sibling of `ClientException`, not a child of it. If you catch `ClientException` broadly around `tools()`, `callTool()`, and similar calls, keep a separate `catch (JsonRpcException $e)` for structured server errors.
