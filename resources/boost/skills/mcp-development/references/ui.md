# MCP UI Apps Reference

## Architecture Overview

MCP Apps add interactive UI to the Model Context Protocol. The server returns self-contained HTML with all JS/CSS inlined. The host renders it in a sandboxed iframe. Apps communicate back via `createMcpApp()` — a pre-bundled global implementing the MCP UI PostMessage protocol.

```
┌─────────────────────────────────────────────┐
│  Host (Claude, ChatGPT, VS Code)            │
│  ┌───────────────────────────────────────┐  │
│  │  Sandboxed iframe                     │  │
│  │  ┌─────────────────────────────────┐  │  │
│  │  │  Your MCP App (HTML/JS/CSS)     │  │  │
│  │  │  - Rendered by UiResource       │  │  │
│  │  │  - Single self-contained HTML   │  │  │
│  │  │  - Themed via host CSS vars     │  │  │
│  │  └─────────────────────────────────┘  │  │
│  └───────────────────────────────────────┘  │
└──────────────────┬──────────────────────────┘
                   │ MCP Protocol (JSON-RPC)
┌──────────────────▼──────────────────────────┐
│  Laravel MCP Server                         │
│  - UiResource → self-contained HTML         │
│  - Tool #[UiLinked] → triggers UI display   │
│  - resources/read → serves HTML + _meta.ui  │
└─────────────────────────────────────────────┘
```

The server automatically advertises `io.modelcontextprotocol/ui` capability when any `UiResource` is registered. The client declares support in `capabilities.extensions["io.modelcontextprotocol/ui"]` during the initialize handshake.

---

## Server-Side

### UiResource

`UiResource` extends `Resource` with MCP app defaults:

```php
abstract class UiResource extends Resource
{
    protected string $mimeType = 'text/html;profile=mcp-app';
    protected string $defaultUriScheme = 'ui';

    public function handle(): Response
    {
        return Response::view('mcp.'.Str::kebab(class_basename(static::class)));
    }

    public function uiMeta(): UiMeta
    {
        return new UiMeta;
    }
}
```

Minimal case — empty class, entire app lives in the Blade view:

```php
class DashboardApp extends UiResource {}
```

Auto-renders `resources/views/mcp/dashboard-app.blade.php`.

Override `handle()` only when passing server-side data:

```php
class AnalyticsDashboard extends UiResource
{
    public function handle(): Response
    {
        return Response::view('mcp.analytics-dashboard', [
            'metrics' => Metric::latest()->take(10)->get(),
            'totalUsers' => User::count(),
        ]);
    }
}
```

`Response::view($view, $data = [])` renders a Blade view and wraps it in an HTML response.

### UiMeta Configuration

The simplest way to configure UI metadata is via the `#[UiMeta]` attribute directly on your resource class:

```php
use Laravel\Mcp\Server\Attributes\UiMeta;
use Laravel\Mcp\Server\Ui\Permission;

#[UiMeta(
    connectDomains: ['https://api.stripe.com'],
    permissions: [Permission::Camera, Permission::ClipboardWrite],
    prefersBorder: true,
)]
class PaymentsResource extends UiResource {}
```

For dynamic or computed configuration, override `uiMeta()` instead:

```php
public function uiMeta(): UiMeta
{
    return UiMeta::make()
        ->csp(Csp::make()->connectDomains(config('services.api.domains')))
        ->permissions(Permissions::make()->allow(Permission::Camera))
        ->domain('sandbox.example.com');
}
```

#### Permission Enum

Use the `Permission` enum for type-safe permission configuration:

```php
use Laravel\Mcp\Server\Ui\Permission;

Permission::Camera        // 'camera'
Permission::Microphone    // 'microphone'
Permission::Geolocation   // 'geolocation'
Permission::ClipboardWrite // 'clipboardWrite'
```

#### Csp

Controls what external domains the iframe can access:

```php
Csp::make()
    ->connectDomains(['https://api.example.com'])    // fetch, XHR, WebSocket origins
    ->resourceDomains(['https://cdn.example.com'])   // images, scripts, fonts, media
    ->frameDomains(['https://embed.example.com'])    // nested iframe origins
    ->baseUriDomains(['https://base.example.com']);  // base URI origins
```

#### Permissions

```php
Permissions::make()->allow(Permission::Camera, Permission::ClipboardWrite);

Permissions::make()
    ->camera()
    ->microphone()
    ->geolocation()
    ->clipboardWrite();
```

Each enabled permission serializes as `"camera": {}` per the MCP spec.

#### UiMeta

```php
UiMeta::make()
    ->csp(Csp::make()->connectDomains([...]))
    ->permissions(Permissions::make()->allow(Permission::Camera))
    ->domain('sandbox.example.com')  // dedicated sandbox origin (OAuth/CORS)
    ->prefersBorder(false);
```

`toArray()` omits null fields and empty nested objects.

#### domain

The `domain` field provides a stable origin that external APIs can allowlist for CORS. It is automatically resolved from `config('app.url')` (your `APP_URL` env variable), so most apps need no configuration. Override only when a resource needs a different origin:

```php
#[UiMeta(domain: 'custom.example.com')]
class PaymentsResource extends UiResource {}
```

---

## View Layer

### `<x-mcp::app>` Blade Component

Renders a complete self-contained HTML document with SDK inlined. `createMcpApp` is available globally.

```blade
<x-mcp::app title="Dashboard App">
    <x-slot:head>
        <script type="module">
        createMcpApp(async (app) => {
            document.getElementById('run-btn').addEventListener('click', async () => {
                const result = await app.callServerTool({ name: 'tool-name', arguments: {} });
                document.getElementById('output').textContent = result.content[0]?.text ?? '';
            });
        });
        </script>
    </x-slot:head>

    <div id="app">
        <button id="run-btn">Run</button>
        <p id="output"></p>
    </div>
</x-mcp::app>
```

**Props and slots:**

| Name | Type | Description |
|------|------|-------------|
| `title` | Prop | Sets `<title>`. Optional. |
| `entry` | Prop | Vite entry point to inline (advanced). Optional. |
| `buildDirectory` | Prop | Public build directory. Defaults to `'build'`. |
| `head` | Named slot | Injected into `<head>` after inlined assets. |
| Default slot | Slot | Body content. |
| `$attributes` | Attribute bag | Forwarded to `<body>` (e.g. `class="dark"`). |

Publish the component: `php artisan vendor:publish --tag=mcp-views`.

To pass server-side data to JS, embed it as `data-*` attributes:

```blade
<div id="app" data-users="{{ $users->toJson() }}">
    ...
</div>
```

```js
const users = JSON.parse(document.getElementById('app').dataset.users);
```

---

## Client-Side

### createMcpApp

Pre-bundled and inlined automatically — no npm install or imports required.

```js
createMcpApp(async (app) => {
    // app is ready — connection established, theming applied
});
```

### app.callServerTool()

```js
const result = await app.callServerTool({
    name: 'get-analytics',
    arguments: { dateRange: '7d', metric: 'pageviews' },
});

// result.content: [{ type: 'text', text: '...' }, ...]
// result.isError: false
const text = result.content[0]?.text ?? '';
```

### app.sendMessage()

Send a message to the model (creates a conversation turn):

```js
await app.sendMessage({
    role: 'user',
    content: [{ type: 'text', text: 'User submitted the form.' }],
});
```

### app.getHostContext()

```js
const ctx = app.getHostContext();
ctx?.theme;     // 'light' | 'dark'
ctx?.locale;    // 'en-US'
ctx?.timeZone;  // 'America/New_York'
```

---

## Host Theming

`createMcpApp` automatically applies host CSS variables to `:root` on connect and on context change.

**Available variable categories:**
- `--color-background-{primary|secondary|tertiary|inverse|ghost|info|danger|success|warning|disabled}`
- `--color-text-{primary|secondary|tertiary|inverse|info|danger|success|warning|disabled|ghost}`
- `--color-border-{primary|secondary|tertiary|inverse|ghost|info|danger|success|warning|disabled}`
- `--font-sans`, `--font-mono`, `--font-weight-{normal|medium|semibold|bold}`
- `--font-text-{xs|sm|md|lg}-size`, `--font-heading-{xs|sm|md|lg|xl|2xl|3xl}-size`
- `--border-radius-{xs|sm|md|lg|xl|full}`
- `--shadow-{hairline|sm|md|lg}`

Always provide fallback values — use `light-dark()` for theme-aware defaults:

```css
:root {
    --color-background-primary: light-dark(#ffffff, #171717);
    --color-text-primary: light-dark(#171717, #fafafa);
    --color-text-secondary: light-dark(#525252, #a3a3a3);
    --color-border-primary: light-dark(#e5e5e5, #404040);
    --font-sans: system-ui, -apple-system, sans-serif;
    --border-radius-md: 8px;
}

body {
    font-family: var(--font-sans);
    background: var(--color-background-primary);
    color: var(--color-text-primary);
    margin: 0;
}

.card {
    background: var(--color-background-secondary);
    border: 1px solid var(--color-border-primary);
    border-radius: var(--border-radius-md);
    padding: 1rem;
}
```

---

## Tool-to-UI Linking

### #[UiLinked] Attribute

Associates a Tool with a UI Resource. When the tool is called, the host fetches and renders the linked resource.

```php
use Laravel\Mcp\Server\Attributes\UiLinked;

// Both model and app can call this tool (default)
#[UiLinked(resource: DashboardApp::class)]
class ShowDashboard extends Tool { ... }

// Only the app can call this tool (private to the UI)
#[UiLinked(resource: DashboardApp::class, visibility: ['app'])]
class RefreshDashboardData extends Tool { ... }
```

**Visibility:**

| Visibility | Model | App | Use case |
|-----------|-------|-----|----------|
| `['model', 'app']` | Yes | Yes | Primary tools that trigger UI display |
| `['app']` | No | Yes | Backend actions the UI calls (refresh, save, paginate) |
| `['model']` | Yes | No | Model-only tools linked to a UI |

### Primary + Private Pattern

```php
#[UiLinked(resource: DashboardApp::class)]
class ShowDashboard extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::text('Dashboard loaded.');
    }
}

#[UiLinked(resource: DashboardApp::class, visibility: ['app'])]
class GetDashboardMetrics extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::json(Metric::latest()->take(50)->get());
    }
}
```

---

## Asset Pipeline

For simple apps, write JS inline in Blade — no Vite needed. Use the `entry` prop for TypeScript, npm packages, or CSS frameworks.

MCP apps run in sandboxed iframes, so standard `@vite()` `<script src="...">` tags won't load. The `<x-mcp::app>` component reads compiled Vite output from disk and inlines it directly.

```js
// vite.config.js
laravel({
    input: [
        'resources/js/mcp/dashboard.js',
    ],
})
```

```blade
<x-mcp::app title="Dashboard" entry="resources/js/mcp/dashboard.js">
    <div id="app">...</div>
</x-mcp::app>
```

`createMcpApp` is still available as a global — the pre-built SDK is inlined before your bundle.

**Dev workflow** (no HMR): edit files → `npm run build` → refresh in host. For faster iteration: `npx vite build --watch`.

---

## Testing

```php
it('returns html content', function () {
    MyServer::readResource(DashboardApp::class)
        ->assertSee('<div id="app">');
});

it('has correct mime type and uri scheme', function () {
    $resource = new DashboardApp;
    $data = $resource->toArray();

    expect($data['mimeType'])->toBe('text/html;profile=mcp-app')
        ->and($data['_meta']['ui'])->toBeArray()
        ->and($resource->uri())->toStartWith('ui://');
});

it('configures uimeta correctly', function () {
    $meta = (new DashboardApp)->resolvedUiMeta();

    expect($meta['csp']['connectDomains'])->toContain('https://api.example.com')
        ->and($meta['permissions'])->toHaveKey('clipboardWrite');
});

it('includes ui metadata in tool listing', function () {
    MyServer::listTools()->assertSee('show-dashboard');
});
```
