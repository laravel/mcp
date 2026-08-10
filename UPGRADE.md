# Upgrade Guide

## Upgrading To 1.x From 0.x

Laravel MCP 1.x implements MCP revision [`2026-07-28`](https://modelcontextprotocol.io/specification/2026-07-28). Existing server registrations, server classes, tools, prompts, resources, authentication middleware, and fluent server tests do not require structural changes.

### MCP Client Compatibility

**Likelihood Of Impact: High**

Laravel MCP servers now support only MCP `2026-07-28`. Before upgrading a server, verify that every client connecting to it supports this protocol revision. Older clients that rely on `initialize`, `notifications/initialized`, `ping`, protocol sessions, or server-initiated requests cannot communicate with a Laravel MCP 1.x server.

The Laravel MCP client remains compatible with `2025-11-25` and `2025-06-18` servers. It opens every connection with `server/discover`, as the specification requires of a client that spans both eras, and falls back to the `initialize` handshake only when the server's answer is not a recognized `2026-07-28` error. Modern servers therefore cost no extra requests. Opening the other way around would pin a server that speaks both eras to the older revision for the life of the connection, because such a server chooses its era from the client's first request. Timeouts, unavailable endpoints, and disconnected transports are surfaced as errors rather than treated as a legacy server.

When the server answers with `UnsupportedProtocolVersionError`, or lists its versions through `server/discover`, the client picks a version both sides support and retries with that instead of guessing.

You may pin the protocol version when the server type is known:

```php
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Facades\Mcp;

Mcp::client('everything')->withProtocolVersion(ProtocolVersion::LATEST)->tools();
Mcp::client('everything')->withProtocolVersion(ProtocolVersion::V2025_11_25)->tools();
```

Pinning a legacy version skips the `server/discover` probe and saves a round trip against a known `2025-11-25` or `2025-06-18` server. Pinning `ProtocolVersion::LATEST` states that the server is modern and skips the legacy fallback entirely. The resolved era is retained when reconnecting and is probed again only if the remembered assumption later fails. The client's `ping()` method remains available for legacy servers; Laravel MCP 1.x servers return a method-not-found error.

For discovery connections, `initializeResult()` returns `null`. Use `discoverResult()` for the server's discovery response and `protocolVersion()` for the resolved protocol version.

The `Laravel\Mcp\Server\Methods\Initialize` and `Laravel\Mcp\Server\Methods\Ping` classes have been removed. Applications that import these classes or register them in a custom `$methods` array should remove those registrations. Use the automatically registered `Laravel\Mcp\Server\Methods\Discover` method for modern server discovery.

### Session APIs Have Been Removed

**Likelihood Of Impact: Medium**

MCP `2026-07-28` removes protocol-level sessions. Laravel MCP no longer issues or reads the `MCP-Session-Id` header. If your application uses the following APIs, update it as indicated:

| Previous API | Upgrade path |
| ------------ | ------------ |
| `Request::sessionId()` | Pass an explicit state handle as a tool or prompt argument. |
| `Request::setSessionId()` | Remove the call. |
| `Server::generateSessionId()` | Remove the call. |
| `SessionInitialized` event | Move the work into the tool, prompt, or resource that requires it. |
| `Laravel\Mcp\Server\Contracts\Transport::sessionId()` | Remove the method and any reliance on session state. |
| `Laravel\Mcp\Server\Contracts\Transport::send(string $message, ?string $sessionId)` | Use `send(string $message)`. |
| `JsonRpcRequest::from(array $json, ?string $sessionId)` | Use `JsonRpcRequest::from(array $json)`. |
| `JsonRpcRequest::__construct(..., ?string $sessionId)` | Remove the session argument. |
| `Request::__construct(array $arguments, ?string $sessionId)` | The second argument is now `?array $meta`. |
| `Laravel\Mcp\Server\Transport\HttpTransport::__construct($request, $sessionId)` | Use `HttpTransport::__construct($request)`. |
| `Laravel\Mcp\Server\Transport\StdioTransport::__construct($sessionId)` | Use `StdioTransport::__construct()`. |

Applications may remain stateful, but state must be represented explicitly rather than inferred from a connection. Treat state handles as identifiers, not authorization credentials, and authorize them against the current user on every request.

Custom server transports should remove their reliance on session state. An implementation that retains an optional `$sessionId` argument on `send()` remains compatible with the contract, but Laravel MCP no longer supplies a session ID.

### Custom Client Transports

**Likelihood Of Impact: Low**

Custom implementations of `Laravel\Mcp\Client\Contracts\Transport` must update their `send` method:

```php
public function send(string $message, array $headers = []): void;
```

The additional `$headers` contain generated header names and encoded values, such as `Mcp-Param-*` headers. Forward them unchanged. Custom HTTP transports are also responsible for generating the protocol headers described below from the current protocol version and JSON-RPC message. The first-party HTTP and stdio transports handle these requirements automatically.

### Direct HTTP and JSON-RPC Integrations

**Likelihood Of Impact: Low**

No changes are required when using Laravel MCP's first-party server routes and client transports. Applications that send raw HTTP or JSON-RPC requests must account for the new stateless lifecycle.

Every supported JSON-RPC method call must include the protocol version and client capabilities in `params._meta`. Client identity is optional but recommended:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/list",
  "params": {
    "_meta": {
      "io.modelcontextprotocol/protocolVersion": "2026-07-28",
      "io.modelcontextprotocol/clientCapabilities": {},
      "io.modelcontextprotocol/clientInfo": {
        "name": "Acme Client",
        "version": "1.0.0"
      }
    }
  }
}
```

A missing metadata member returns `-32602`, an unsupported protocol version returns `-32022`, and an operation that requires an undeclared client capability returns `-32021`.

Non-`initialize` HTTP requests carrying an `id` must include `MCP-Protocol-Version` and `Mcp-Method`. Calls to `tools/call`, `prompts/get`, and `resources/read` must also include `Mcp-Name`; use the requested URI for `resources/read`. The mirrored headers must agree with the JSON-RPC body. Unsafe `Mcp-Name` and `Mcp-Param-*` values are encoded using the `=?base64?...?=` format. Requests should accept both `application/json` and `text/event-stream` responses.

Laravel MCP rejects missing or contradictory headers with HTTP `400` and JSON-RPC error `-32020`. Method-not-found errors return HTTP `404`, internal errors return `500`, and other protocol errors return `400`. HTTP `GET` and `DELETE` requests to modern MCP endpoints return `405`.

Interrupted SSE responses are not resumable. Reissue the operation with a new JSON-RPC request ID instead of using `Last-Event-ID`.

### Response Metadata and Caching

**Likelihood Of Impact: Low**

Laravel MCP automatically adds `resultType` and server identity metadata to successful responses. Initial complete results from `server/discover`, `tools/list`, `prompts/list`, `resources/list`, `resources/templates/list`, and `resources/read` also contain `ttlMs` and `cacheScope`:

```json
{ "resultType": "complete", "ttlMs": 0, "cacheScope": "private", "tools": [] }
```

The package client accepts these fields automatically. Update strict response schemas, proxies, caches, and raw response assertions to accept them. Legacy results without `resultType` should be treated as complete results.

The default cache settings are `ttlMs: 0` and `cacheScope: private`. They may be configured on the server:

```php
use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Server;

class WeatherServer extends Server
{
    protected int $cacheTtlMs = 300000;

    protected CacheScope $cacheScope = CacheScope::PUBLIC;
}
```

Only use `CacheScope::PUBLIC` when every cacheable response is independent of the authenticated user, access token, and authorization context. The Laravel MCP client accepts cache hints but does not cache responses based on them.

The error returned when `resources/read` cannot resolve a resource URI has also changed from `-32002` to `-32602`.

### MCP Apps

**Likelihood Of Impact: Low**

Servers that register an `AppResource` continue to advertise MCP Apps support automatically. No changes are required for normal app resources or tools using `#[RendersApp]`.

If your server manually advertises MCP Apps support, replace `Server::CAPABILITY_UI` with `Server::EXTENSION_UI` and register it as an extension:

```php
protected function boot(): void
{
    $this->addExtension(self::EXTENSION_UI);
}
```

The advertisement now appears under `capabilities.extensions['io.modelcontextprotocol/ui']` instead of the top-level capabilities object.

MCP Apps are an optional extension. Tools that render an app should continue returning meaningful core MCP content for clients that do not advertise the UI extension.

### Change Notifications

**Likelihood Of Impact: Low**

`resources/subscribe`, `resources/unsubscribe`, and the standalone HTTP `GET` stream are removed. Change notifications are now delivered on a long-lived `subscriptions/listen` request instead.

Laravel MCP never implemented the removed methods or the `GET` stream, and does not implement `subscriptions/listen`. The `resources` capability advertises neither `listChanged` nor `subscribe`, which the specification permits: servers may advertise either feature independently, together, or neither. No changes are required.

### Tool Parameter Headers

**Likelihood Of Impact: Low**

Remote servers may annotate tool arguments with `x-mcp-header` to have their values mirrored into `Mcp-Param-*` request headers, so that intermediaries can route on them without parsing the request body. Supporting this is required of clients, so the Laravel MCP client mirrors annotated arguments automatically when calling such a tool.

Mirrored arguments must resolve to `string`, `integer`, or `boolean` schema properties, and integer values must remain within JavaScript's safe integer range. When receiving remote tools, the Laravel MCP client omits tools with invalid `x-mcp-header` annotations and logs a warning instead of failing the entire tool list.

Annotating arguments is optional for servers, and Laravel MCP servers do not currently do so. No changes are required to your own tools.

### Authentication and Testing

**Likelihood Of Impact: Low**

Passport, Sanctum, route middleware, bearer tokens, authorization logic, and `Request::user()` continue to work without API changes. Because requests are stateless, authentication and authorization are evaluated independently for every request.

The documented fluent helpers for testing tools, prompts, resources, and completions remain unchanged. Raw HTTP and JSON-RPC tests must include the new metadata and headers and should account for the new HTTP statuses and response fields described above.

### Before Deploying

- [ ] Verify every external client supports MCP `2026-07-28`.
- [ ] Test web and local servers with the official [MCP Inspector](https://github.com/modelcontextprotocol/inspector).
- [ ] Audit custom transports and raw protocol integrations for per-request metadata and HTTP headers.
- [ ] Confirm state handles are authorized against the current user on every request.
- [ ] Confirm public cache hints are not used for user-specific or authorization-specific responses.
