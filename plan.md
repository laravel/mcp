
# MCP Client — Shipping Plan

> Status: draft, expected to be refined.
> Spec: <https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization>
> Reference client matrix: <https://modelcontextprotocol.io/clients>



| In scope | Out of scope |
|---|---|
| Tools | Sampling / Roots / Elicitation |
| Resources | `resources/subscribe` + push notifications |
| Prompts | Tasks / Apps / Discovery beyond `initialize` |
| OAuth `client_credentials` | DCR (RFC 7591) |
| OAuth `authorization_code` + PKCE | CIMD |
| stdio + Streamable HTTP transports | Enterprise-Managed Authorization |

## Design principle

**99% dev-ex, 1% extensibility, code-first.** Clients are constructed via
a fluent builder in code, not a config array. A spec-compliant MCP server
should work with only a `client_id`. Every escape hatch is a builder
method — no separate facade surface for "advanced".

The package facade is two methods:

- `Mcp::registerClientFor(string $name, Closure $factory, int|false $cache = 3600)` —
  name a client; `$cache` is the list-cache TTL in seconds (default on, 1h),
  `false` to disable.
- `Mcp::client(string $name)` — resolve it, memoized per request.

`config/mcp.php` holds only app-wide settings like the protocol
version. Servers are always registered in code via `registerClientFor()`
— there is no array-driven config path.

OAuth is built on
[`league/oauth2-client`](https://github.com/thephpleague/oauth2-client)
— a hard composer dependency, not optional. We get the wire protocol,
PKCE primitives, and grant flows from the league package; we add the
MCP-spec layer on top (RFC 9728/8414 discovery, RFC 8707 resource
parameter, `WWW-Authenticate` parsing, 403 step-up).

## Usage — source of truth

### Building a client

Client::local('npx', ['-y', '@modelcontextprotocol/server-everything']);

// HTTP, no auth
Client::web('https://mcp.example.com/mcp');

// HTTP with a static bearer header
Client::web('https://mcp.example.com/mcp')
    ->withToken(env('REMOTE_TOKEN'));

// HTTP with OAuth client_credentials (client + secret = machine-to-machine)
Client::web('https://mcp.github.com')
    ->withOauth(env('GH_CLIENT_ID'), env('GH_CLIENT_SECRET'));

// HTTP with OAuth authorization_code (client_id only = delegated user)
Client::web('https://mcp.notion.com')
    ->withOauth(env('NOTION_CLIENT_ID'));
```

`Client::web()` / `Client::local()` return a `Client` instance that
hasn't connected yet. Builder methods mutate and return the same
instance. First operational call (`tools()`, `callTool()`, …) lazily
connects; `->connect()` is available for fail-fast.

### Builder methods

```php
Client::web($url)
    ->withToken($token)                    // static bearer header (mutually exclusive with oauth)
    ->oauth($clientId)                     // authorization_code (client_id only)
    ->oauth($clientId, $clientSecret)      // client_credentials
    ->oauth($clientId, scope: 'repo:read workspace:read')    // override discovered scope
    ->oauth($clientId, onAuthRequired: fn (AuthorizationRequiredException $e) =>
        redirect()->away($e->authorizationUrl()))           // interactive 401 handler (authorization_code)
    ->forUser(fn () => auth()->user())     // per-user tokens (authorization_code); omit for shared/app identity
    ->headers(['X-Trace' => '...'])        // extra request headers
    ->withTimeout(30)                      // forwards to Transport::setTimeoutSeconds()
    ->connect();                           // optional eager connect
```

List caching is not a builder method — it is the `cache:` argument of
`registerClientFor()`, because it needs the stable name to key on. Inline
clients (`Client::web` / `Client::local`) are never cached — `tools()`
fetches from the server on every call.

`scope` and `onAuthRequired` are arguments to `oauth()`, not standalone
builder methods — so they are unreachable unless you are actually
configuring OAuth. Full signature:

```php
oauth(
    string $clientId,
    ?string $clientSecret = null,
    ?string $scope = null,
    ?Closure $onAuthRequired = null,
): static
```

`withToken()` (static bearer) and `oauth()` are mutually exclusive —
setting both throws at build time.

Forgetting cached tokens (e.g. "Disconnect this integration" buttons):

```php
Mcp::client('notion')->forgetTokens();
```

Inspecting current token state (e.g. UI showing "expires 5:42pm"):

```php
$tokens = Mcp::client('notion')->tokens();   // ?TokenSet, read-only
```

Checking whether a human still needs to connect — a cheap, cache-only
read that never touches the network or fetches tools:

```php
Mcp::client('notion')->needsAuthorization();   // bool
```

### Talking to a server

```php
$client = Client::local('npx', ['-y', '@modelcontextprotocol/server-everything']);

$client->serverInfo();
$client->serverCapabilities();

$client->tools();                                  // Collection<ClientTool>
$client->callTool('add', ['a' => 1, 'b' => 2]);    // ToolResult

$client->resources();                              // Collection<ClientResource>
$client->resourceTemplates();
$client->readResource('file:///example.txt');      // ResourceContents

$client->prompts();                                // Collection<ClientPrompt>
$client->getPrompt('summarize', ['text' => '...']);// PromptResult

$client->ping();
$client->clearCache();
$client->disconnect();
```

`ToolResult`, `ResourceContents`, and `PromptResult` are value objects
with `__toString()` so they pass through any string context cleanly;
each also exposes typed accessors (`->text()`, `->isError()`,
`->content()`, `->messages()`, etc.) when you need to introspect.

### Named clients

For integrations you reuse, register the builder once. The package
memoizes the resolved client per request and disconnects on terminate.
Registration takes an optional `cache:` argument for the per-name
list-cache policy:

```php
Mcp::registerClientFor('notion', fn () =>
    Client::web('https://mcp.notion.com')->oauth(env('NOTION_CLIENT_ID'))
);

Mcp::registerClientFor('everything', fn () =>
    Client::local('npx', ['-y', '@modelcontextprotocol/server-everything'])
);

// anywhere
Mcp::client('notion')->tools();
Mcp::client('everything')->callTool('add', ['a' => 1, 'b' => 2]);
```

Named clients cache their tools / resources / prompts lists by default —
keyed by name (and by user when `->forUser()`-scoped), so the cache
survives across requests. Tune or disable it with the `cache:` argument:

```php
// default: lists cached for 1h
Mcp::registerClientFor('notion', fn () =>
    Client::web('https://mcp.notion.com')->oauth(env('NOTION_CLIENT_ID'))
);

// override the TTL (seconds)
Mcp::registerClientFor('notion', fn () =>
    Client::web('https://mcp.notion.com')->oauth(env('NOTION_CLIENT_ID')),
    cache: 1800,
);

// fast-changing server — no cross-request cache
Mcp::registerClientFor('dev', fn () =>
    Client::local('php', ['mcp.php']),
    cache: false,
);

// invalidate on demand (also auto-busts on tools/list_changed)
Mcp::client('notion')->clearCache();
```

Caching mirrors the token lifecycle: policy is set once at registration
(like `oauth()`), and runtime operations live on the resolved client.
`clearCache()` is the counterpart to `forgetTokens()` — scope-aware (a
`->forUser()` client clears only the current user's entries) and it never
opens a connection.

Inline clients (built without `registerClientFor`) have no stable name to
key on, so they are never cached — `tools()` / `resources()` / `prompts()`
fetch from the server on every call. Caching is exclusive to named clients
resolved through `Mcp::client()`.

### Interactive auth (`authorization_code`)

**Default — one line, no controller.**

```php
// routes/ai.php
Mcp::oauthClientRoutes();

// anywhere — the first call without a token redirects through
// the package's /mcp/{server}/connect → AS → /mcp/{server}/callback
Mcp::client('notion')->tools();
```

**Advanced — handle the redirect yourself.**

```php
Mcp::registerClientFor('notion', fn () =>
    Client::web('https://mcp.notion.com')
        ->oauth(env('NOTION_CLIENT_ID'), onAuthRequired: fn (AuthorizationRequiredException $e) =>
            redirect()->away($e->authorizationUrl()))
);

return Mcp::client('notion')->tools();
// the onAuthRequired closure handles the redirect; control returns to
// the callback route, which retries the call transparently
```

### Shared vs per-user tokens

`authorization_code` tokens can be scoped two ways. By default a client
stores one token per server name — the right model for a service account
or back-office integration where the whole app acts as a single identity:

```php
// shared — every caller uses the same Notion connection
Mcp::registerClientFor('notion', fn () =>
    Client::web('https://mcp.notion.com')->oauth(env('NOTION_CLIENT_ID'))
);
```

Call `->forUser()` to give each end-user their own token — the model for
a multi-user product where everyone connects their own account:

```php
// per-user — token keyed by the authenticated user
Mcp::registerClientFor('notion', fn () =>
    Client::web('https://mcp.notion.com')
        ->oauth(env('NOTION_CLIENT_ID'))
        ->forUser(fn () => auth()->user())
);

// inside an agent's tool list — reads the current user's token transparently
public function tools(): iterable
{
    return [...Mcp::client('notion')->tools()];
}
```

`forUser()` accepts an `Authenticatable`, a raw id, or a `Closure` that
resolves one lazily — use the closure form for registered clients so the
per-request memoized instance binds to the current user. For a queued job
or an admin acting on someone's behalf, pass the user explicitly; the
token must already exist from an earlier interactive connect:

```php
Client::web('https://mcp.notion.com')
    ->oauth(env('NOTION_CLIENT_ID'))
    ->forUser($job->user)
    ->callTool('search', ['q' => 'roadmap']);
```

Disconnecting one user's integration clears only their token:

```php
Mcp::client('notion')->forUser($user)->forgetTokens();
```

Rules:

- Omitting `forUser()` is shared mode; calling it is per-user. There is
  no third state.
- `forUser()` on a registered client returns a fresh scoped clone — it
  never mutates the memoized instance, so cross-user work in one request
  (admin "act as", batch) is safe. The user is resolved once when the
  scoped client is first used, not re-read per call.
- If `forUser()` is set but resolves to no user (`auth()->user()` is null
  in a guest request or a job), the next token access throws
  `UserIdentityRequiredException` — the app token is never silently shared
  with an unidentified caller. Explicit `forUser(null)` throws at build
  time.
- `forUser()` only applies to `authorization_code`. Combining it with
  `client_credentials` or `withToken()` throws at build time — those are
  app-level identities, not per-user.

### Gating on auth readiness

Registration happens in a service provider; the connection — and the auth
check — happens later, the first time something calls `tools()`,
`callTool()`, etc. For an `authorization_code` client with no usable
token, that lazy connect throws **before** any tools are fetched:
`AuthorizationRequiredException` (a human must connect) or
`UserIdentityRequiredException` (a `forUser` client with no current user).
`client_credentials` and `withToken` clients never need a human, so they
self-serve silently.

When MCP tools feed an AI agent, gate on readiness up front rather than
letting the exception surface mid-build. `needsAuthorization()` checks
each integration cheaply (cache only, no fetch), so you can surface every
missing one at once and only run the agent when they are all ready:

```php
$pending = collect(['notion', 'github'])
    ->filter(fn ($name) => Mcp::client($name)->needsAuthorization())
    ->map(fn ($name) => ['service' => $name, 'url' => route('mcp.connect', $name)])
    ->values();

if ($pending->isNotEmpty()) {
    return response()->json(['type' => 'connect_required', 'services' => $pending]);
}

return $agent->run($message);   // every client ready → builds + runs cleanly
```

Without a gate, `tools()` throws when auth is missing — the caller
chooses whether to fail loud (the default) or catch it and run
best-effort without that server's tools.

### Re-exposing a remote tool through your own server

`ClientTool` extends `Server\Tool`, so external tools can be registered
on the local MCP server alongside your own. `tools()` is just an
iterable — mix MCP client tools with locally-defined ones:

```php
public function tools(): iterable
{
    return [
        ...Mcp::client('github-mcp')->tools(),
        new MyOwnTool,
    ];
}
```

### Dynamic / multi-tenant

The builder is the API — there is no separate "dynamic" path.

```php
// per-tenant, inline (caller owns disconnect; PHP shutdown is a safety net)
$client = Client::web($tenant->mcp_url)
    ->oauth($tenant->client_id, $tenant->client_secret);

try {
    $client->tools();
} finally {
    $client->disconnect();
}

// per-tenant, registered — bake the tenant id into the name so each
// tenant gets its own cache scope (register from a tenancy-aware service
// provider or middleware, so the closure captures the right tenant)
Mcp::registerClientFor("tenant:{$tenant->id}", fn () use ($tenant) =>
    Client::web($tenant->mcp_url)
        ->oauth($tenant->client_id, $tenant->client_secret)
);

Mcp::client("tenant:{$tenant->id}")->tools();
```

The list cache is keyed by registered name, so a fixed name like
`'tenant'` shared across tenants would leak one tenant's cached tools to
the others. Either bake the tenant id into the registered name (as
above), or pass `cache: false` to that registration to skip caching.

### Testing

```php
Mcp::fake('notion', tools: [
    new FakeTool('search', returning: ['result' => 'mocked']),
]);

// app code unchanged
Mcp::client('notion')->callTool('search', ['q' => 'x']);

// in test
Mcp::client('notion')->assertCalled('search', ['q' => 'x']);
```

### CLI

```bash
php artisan mcp:client notion
# prints server info, transport, auth state, and lists tools / resources / prompts
```

### Config

`config/mcp.php` holds the protocol version (and other app-wide knobs
later). Servers themselves are always registered via `registerClientFor()`
in a service provider — there is no `servers` array in config.

```php
// config/mcp.php
return [
    'protocol_version' => '2025-11-25',
];
```

## Stages

Strictly additive — each is its own PR.

| Stage | Status | Scope |
|---|---|---|
| 1a | done | Transport + protocol core: `Client` / `WebClient` / `Protocol`, `StdioTransport` + `HttpTransport`, `Initialize` / `Ping`, connect / disconnect / lazy-connect / `__destruct` / `withTimeout`. |
| 1b | partial | Builder & lifecycle. Done: `Client::local()` / `Client::web()` builders, lazy connect, defensive `__destruct`, `Mcp::registerClientFor()` / `Mcp::client()` / `ClientManager` (named clients, per-request memoization, terminate-disconnect), `registerClientFor(cache:)` default-on list cache via internal `Client::asRegisteredClient()`. Not done: `Mcp::fake()` / `FakeClient`, `php artisan mcp:client` CLI, `forUser()` per-user list-cache scope. |
| 2 | done | Tools: `tools()` (`ListTools`, paginated), `callTool()` (`CallTool`), `Tool` primitive, `ToolResult` (`->text()`, `->isError`, `->structuredContent`, `__toString()`). Static-bearer HTTP auth (`withToken()`) only; OAuth deferred to Stage 4. `ToolResult::content()` Collection accessor still to add. |
| 3 | to do | Resources + Prompts (`ClientResource`, `ClientPrompt`, list / read / get methods, `ResourceContents`, `PromptResult`). |
| 4 | to do (rewrite of `add_mcp_client`) | OAuth `client_credentials` + MCP 2025-11-25 spec compliance + cache-backed token storage + `Client::forgetTokens()` + `Client::tokens()`. |
| 5 | to do (rewrite of `add_mcp_client`) | OAuth `authorization_code` + `onAuthRequired:` + `Mcp::oauthClientRoutes()` package controller + `->forUser()` per-user tokens (`mcp-auth:{server}:user:{id}`) + `needsAuthorization()` + `UserIdentityRequiredException`. |

## Implementation notes

- **Stage 1a — transport & protocol core.** A `Protocol` object wraps
  the `Transport` and is owned by the
  `Client`. `connect()` / `disconnect()` idempotent; `ping()` lazily
  connects. `__destruct()` calls `disconnect()` defensively.
  Initialize handshake sends `protocolVersion: ProtocolVersion::LATEST`,
  `capabilities: (object) []` (encoded as `{}` on the wire per spec),
  and parses `serverInfo` via `Schema\Implementation::from()`.
  Notification frames received during a `call()` are skipped; server-initiated
  `ping` requests are answered inline with an empty result.
  **Timeout lives on the transport, not the client.** `Transport`
  interface has `setTimeoutSeconds(float): void`; `Client::withTimeout($s)`
  is a builder that forwards. On timeout the transport auto-disconnects
  (calls its own `disconnect()`) then throws `ClientException` — the
  caller never sees a half-open connection.
  `StdioTransport` uses `symfony/process` directly (not Illuminate's
  `Process` facade), with `Process::waitUntil()` instead of a userland
  `usleep` poll loop — `waitUntil` blocks via `stream_select` on the
  pipe FDs inside Symfony's `ProcessPipes::readAndWrite`. Idle timeout
  uses Symfony's `setIdleTimeout()`; `ProcessTimedOutException` is
  translated to `ClientException`. Total process runtime is unbounded
  (`setTimeout(null)`) so the subprocess can live as long as the client
  uses it.

- **Stage 1b — builder & lifecycle (remaining).** `Client::local()` /
  `Client::web()` return a mutable `Client` instance (same class — no
  separate builder type, no `->build()` step). Builder methods are
  setters that return `$this`. First operational call lazily connects.
  Named clients live in `ClientManager` (singleton) with a terminating
  callback that disconnects them. Inline clients (built without
  `Mcp::registerClientFor`) are caller-owned; `Client::__destruct()` calls
  `disconnect()` defensively so forgotten stdio processes still get
  reaped on PHP shutdown.
  `Mcp::registerClientFor('name', $factory, cache:)` overwrites on
  duplicate name; the `cache:` argument (TTL seconds, default 3600, `false`
  to disable) sets the list-cache policy for that name. Factory closure
  runs lazily at `Mcp::client('name')` time and the resolved instance is
  memoized for the rest of the request.
  Stdio config is `command: string` + `args: string[]` only — no single
  shell-string form (matches Claude Desktop / Cursor / VS Code conventions).
  `Mcp::fake('name', tools: [...])` swaps the registered factory with a
  `FakeClient` that records calls and returns canned data;
  `php artisan mcp:client {server}` pings, prints server info, lists
  tools / resources / prompts, and shows token state.

- **Client architecture — one class, no decorator.** `Client` is the single
  concrete class. There is **no** `NamedClient`, no `ClientContract`
  interface, no decorator hierarchy. "Named" is a cache-policy attribute on
  the same `Client` instance, not a separate type. Internal fields:
  `?string $registeredName`, `int|false $listCacheTtl` — both default to
  "off" so an inline `Client` from `Client::web` / `Client::local` never
  caches. (`forUser()` per-user scoping is Stage 5 work; the
  `string|int|null $userKey` field lands then, alongside the matching
  `:user:{id}` segment in `PrimitiveCache::key()`.) `ClientManager::registerClientFor()` stores
  a factory closure that wraps the user-supplied factory and applies
  `$client->asRegisteredClient($name, $cache)` (an `@internal` seam that
  mutates the same instance in place and returns it) before handing the
  client back. No separate `ClientRegistration` class — the manager owns
  registration end-to-end. `tools()` / `resources()` / `prompts()`
  consult a protected `primitiveCache()` that returns a configured
  `PrimitiveCache` only when `registeredName !== null && listCacheTtl > 0`
  — so the "inline
  never caches" rule is enforced by the absence of a name, not by class
  taxonomy. `Mcp::client(): Client`. `clearCache()` lives on `Client` and
  is a no-op for inline (no policy → nothing to flush) — an operational
  invariant, deliberately not a type-level one. Rationale: a decorator
  (`NamedClient`-style) added 50+ lines of pure forwarding for a still-
  small surface and would grow linearly with resources/prompts/OAuth;
  inheritance with a transport handoff (`release()` + `?Transport`) fought
  the `Protocol` ownership model; an interface earned no payoff because
  caching needs raw payloads, not the hydrated method contract. Revisit
  only if a second cross-cutting concern needs to wrap the full surface
  independently of list caching (e.g. retry/observability decorators).

- **List cache layout** — `tools()` / `resources()` / `resourceTemplates()`
  / `prompts()` cache their list payloads. Only named clients resolved via
  `Mcp::client()` are cached, since the key needs the stable name; inline
  clients (`Client::web` / `Client::local`) are never cached — every list
  call fetches live.
    - **On by default** for named clients (TTL 3600s). The `cache:`
      argument of `registerClientFor()` overrides the TTL; `cache: false`
      disables it.
    - **Driver**: `config('cache.default')`, same store strategy as the
      token cache.
    - **Key**: `mcp-list:{server}:{kind}` (kind = tools / resources /
      resource-templates / prompts), plus `:user:{id}` when the client is
      `->forUser()`-scoped. Mirrors the `mcp-auth:{server}` scheme rather
      than hashing credentials into the key (so a token refresh doesn't
      churn the list cache).
    - **Value**: the raw list payload (plain arrays, JSON-serializable),
      rehydrated into `ClientTool` / `ClientResource` / `ClientPrompt`
      against the live resolved client on read — the cached data never
      holds a connection. Tool / resource / prompt *calls* are never cached.
    - **Invalidation**: a server `notifications/tools/list_changed` (and
      the resource / prompt equivalents) busts the matching entry;
      `Mcp::client($name)->clearCache()` clears every list entry for the
      resolved scope (the current user's, for a `->forUser()` client) from
      the cache store directly — clearing never opens a connection. The
      surface mirrors tokens: the `cache:` argument configures at
      registration like `oauth()`'s arguments, `clearCache()` is a runtime
      op on the resolved client like `forgetTokens()` — one place per
      lifecycle phase, not two.

- **Stage 2 — result shapes.** `callTool()` returns `ToolResult` —
  value object with `->text()`, `->isError()`, `->content()` (Collection),
  `__toString()`. Application-level tool errors (server returns
  `isError: true`) return the `ToolResult` with `isError === true`;
  never throw. Protocol-level failures still throw `ClientException`.
  Collections everywhere for list operations
  (`Illuminate\Support\Collection<ClientTool>`).

- **Stage 3 — resources & prompts.** No code yet. Add
  `Methods/ListResources`, `Methods/ReadResource`,
  `Methods/ListResourceTemplates`, `Methods/ListPrompts`,
  `Methods/GetPrompt`. Add `ClientResource extends Server\Resource` and
  `ClientPrompt extends Server\Prompt` so the proxy / re-export pattern
  works for them too. `readResource()` returns `ResourceContents` (a
  Collection of `ResourceContent` items, each with `->uri()`,
  `->mimeType()`, `->text()`, `->blob()`, `->isText()`, `->isBinary()`,
  plus `__toString()` for the single-text case). `getPrompt()` returns
  `PromptResult` (`->description()`, `->messages()`, `__toString()`).
  Cache `resources()` / `prompts()` list calls the same way `tools()`
  does.

- **Stage 4 spec items** — all hard MUSTs from the MCP 2025-11-25 auth
  spec that the existing providers don't satisfy:
    - RFC 8707 `resource` parameter on every auth and token request.
    - Multi-endpoint AS discovery (OAuth and OIDC, path and non-path).
    - `/.well-known/oauth-protected-resource` fallback when
      `WWW-Authenticate` lacks `resource_metadata=`.
    - `code_challenge_methods_supported` verification; missing field
      throws `PkceUnsupportedException` from `AuthServerDiscovery`
      (message includes the AS issuer URL).
    - `scope` from `WWW-Authenticate` is authoritative over configured.
    - 403 `insufficient_scope` step-up.
    - HTTPS enforcement on AS endpoints and redirect URIs; loopback
      exception covers `localhost`, `127.0.0.1`, and `::1` only.
      Violations throw at client resolution time, not at first request.
  Discovery is **not** cached — rediscover on every cold path
  (rediscovery only fires when there's no token yet anyway).
  Discovery endpoint fall-through: 404 only; 5xx and network errors
  abort with `DiscoveryException`.

- **Stage 4 ergonomics.** Promote `league/oauth2-client` to a hard
  composer dependency (drop the `class_exists()` guards in the existing
  providers). `->oauth($id, $secret?)` constructs `GenericProvider`
  with discovery-derived endpoints.
  `Client::forgetTokens()` clears the cached token for the resolved
  scope — the shared entry, or just the current user's when the client is
  `->forUser()`-scoped (the per-user "disconnect" button).
  `Client::tokens(): ?TokenSet` is a read-only accessor — returns the
  cached `TokenSet` for that same scope (possibly expired) or `null`;
  never refreshes.
  `Client::needsAuthorization(): bool` answers — without connecting or
  fetching — whether an interactive connect is still required: true only
  for an `authorization_code` client whose scope has no cached token;
  false for `client_credentials` / `withToken` / no-auth (they self-serve)
  and once a token is cached (even an expired-but-refreshable one).
  Deferred from Stage 4: `->resource()` and `->onTokenRequest()`
  builder slots (Vercel / TS SDK power-user hooks) and any BYO storage
  surface — add when a real user need surfaces.

- **Stage 4 default cache layout** — concrete shape of what lands in
  the cache when the user doesn't override:
    - **Driver**: the application's default cache store
      (`config('cache.default')`). Document "use Redis / DB / Memcached
      for multi-server apps; `file` works for single-server, `array`
      will silently lose tokens between requests."
    - **Key**: `mcp-auth:{server}` for shared (default) clients, or
      `mcp-auth:{server}:user:{id}` when the client is scoped with
      `->forUser()` (`{id}` is the user's `getAuthIdentifier()`).
      `{server}` is the name passed to `Mcp::registerClientFor`.
    - **Value**: JSON-serialized `TokenSet::toArray()` (access token,
      refresh token, expires-at as Unix timestamp, scope string),
      passed through `Crypt::encryptString()` before write. Decrypted
      on read; corrupted / undecryptable payloads are treated as
      missing.
    - **TTL**: `expires_in - 30s` clock-skew buffer on the access
      token. Refresh tokens are stored alongside in the same payload
      (single cache entry per server). If the AS doesn't return
      `expires_in`, default to 3600s.
    - **Concurrency**: `Cache::lock("mcp-auth-refresh:{scope}", 10)`
      (where `{scope}` mirrors the cache key — `{server}` or
      `{server}:user:{id}`, so users never contend on each other's lock)
      around the refresh-token grant prevents N parallel requests from
      all refreshing and stomping each other. Lock held only for the
      grant request; callers that miss the lock wait briefly and
      re-read the cache.

  implementation.
  Closure signature for the `onAuthRequired:` argument of `oauth()` is
  `fn (AuthorizationRequiredException $e): mixed`; the exception
  carries auth URL, state, server name, and resource-metadata URL.
  When a token is missing, lazy connect runs discovery (to build the auth
  URL) but throws before sending `tools/list` — tools are never fetched
  on the unauthenticated path. `needsAuthorization()` short-circuits this
  earlier still, from cache alone, with no discovery round-trip.
  `Mcp::oauthClientRoutes()` defaults: prefix `mcp`, middleware
  `['web']` (session is required for D18; auth middleware is the host
  app's call). Single parameterized route pair —
  `GET /mcp/{server}/connect` and `GET /mcp/{server}/callback` —
  controller resolves the client via `Mcp::client($server)` at request
  time, 404 if not registered or not `authorization_code`-shaped.
  `connect` mints a `state`, stashes `{server, user, intended,
  pkce_verifier}` in the session under that `state` with a short TTL (a
  few minutes, so abandoned connects don't accumulate), and redirects to
  the AS; `user` is the client's resolved `forUser` identity (null for
  shared clients). `callback` validates `state`, confirms the
  authenticated user still matches the stashed `user`, exchanges the code,
  and writes the token to the client's scoped key (`mcp-auth:{server}` or
  `mcp-auth:{server}:user:{id}`).
  Post-callback redirect chain: `session('mcp.oauth.intended')` →
  `config('mcp.oauth.success_url')` → `'/'`.
  Error response: redirect to `config('mcp.oauth.error_url')` (or `'/'`
  if unset) with `session('mcp.oauth.error')` flashed carrying the AS
  `error` / `error_description` (or a synthesized message for
  client-side failures like state mismatch or expired PKCE verifier).
  Token scope is shared by default (one entry per server name) — right
  for back-office, service-account, and machine-driven flows; multi-user
  agents opt into per-user tokens with `->forUser()`, which returns a
  scoped clone and never mutates the memoized named instance. A `forUser`
  client that resolves to no user throws `UserIdentityRequiredException`
  rather than fall back to the shared token.

- **Footprint.** `league/oauth2-client` is a hard composer dependency.
  Stdio uses `symfony/process` (constructed directly, not via
  Illuminate's `Process` facade) for `Process::waitUntil()` —
  `stream_select`-backed blocking on the subprocess pipes without a
  userland poll loop. PHP 8.2+ baseline, same as the server. No
  package-level logging hook, no package-level events — userland
  decorates if needed.

## Open questions

- Ship Stages 4 and 5 together, or hold Stage 5 a cycle so the
  package-callback-route docs catch up?
- Multiple `authorization_servers` in PRM: pick `[0]`, or surface as a
  builder method?
- Is CIMD truly out of scope, or do we revisit after surveying which
  MCP servers in the wild require it?
- Refresh-token failure surfacing (deferred from D21): silent clear +
