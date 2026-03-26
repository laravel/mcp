---
name: mcp-development
description: "Use this skill for Laravel MCP development. Trigger when creating or editing MCP tools, resources, prompts, servers, or UI apps in Laravel projects. Covers: artisan make:mcp-* generators (including make:mcp-ui-resource for MCP Apps), routes/ai.php, Tool/Resource/Prompt/UiResource classes, schema validation, shouldRegister(), OAuth setup, URI templates, read-only attributes, MCP debugging, MCP UI apps, the x-mcp::app Blade component, createMcpApp() global (pre-bundled — no npm install needed), default UiResource handle() auto-infers view from class name, Response::view(), UiMeta/Csp/Permissions/defaultUiMeta() configuration, #[UiLinked] attribute, and host theming via CSS variables. Use this whenever the user mentions MCP apps, MCP UI, interactive MCP resources, or building visual interfaces for AI agents."
license: MIT
metadata:
  author: laravel
---
@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# MCP Development

## Documentation

Use `search-docs` for detailed Laravel MCP patterns and documentation.

For MCP UI apps (interactive HTML resources), read `references/app.md` — it covers the full architecture, host theming CSS variables, tool-to-UI linking patterns, and real-world examples.

## Basic Usage

Register MCP servers in `routes/ai.php`:

@boostsnippet("Register MCP Server", "php")
use Laravel\Mcp\Facades\Mcp;

Mcp::web();
@endboostsnippet

### Creating MCP Primitives

```bash
{{ $assist->artisanCommand('make:mcp-tool ToolName') }}            # Create a tool
{{ $assist->artisanCommand('make:mcp-resource ResourceName') }}     # Create a resource
{{ $assist->artisanCommand('make:mcp-prompt PromptName') }}        # Create a prompt
{{ $assist->artisanCommand('make:mcp-server ServerName') }}        # Create a server
{{ $assist->artisanCommand('make:mcp-ui-resource DashboardApp') }} # Create a UI app (2 files)
```

After creating primitives, register them in your server's `$tools`, `$resources`, or `$prompts` properties.

### Tools

@boostsnippet("MCP Tool Example", "php")
use Illuminate\Json\Schema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class MyTool extends Tool
{
    protected string $description = 'Describe what this tool does';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The name parameter')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate(['name' => 'required|string']);

        return Response::text('Hello, '.$request->get('name'));
    }
}
@endboostsnippet

### Registering Primitives in a Server

@boostsnippet("Register Primitives in MCP Server", "php")
use Laravel\Mcp\Server;

class AppServer extends Server
{
    protected array $tools = [
        \App\Mcp\Tools\MyTool::class,
    ];

    protected array $resources = [
        \App\Mcp\Resources\MyResource::class,
    ];

    protected array $prompts = [
        \App\Mcp\Prompts\MyPrompt::class,
    ];
}
@endboostsnippet

## MCP UI Apps

MCP Apps let you build interactive HTML interfaces that AI agents can display to users. `make:mcp-ui-resource` generates two files — a PHP registration stub and a Blade view. The entire app lives in the Blade view.

**PHP class** — just registers the resource, no code needed:
```php
class DashboardApp extends UiResource {}
```
`handle()` is provided by default: auto-infers the view `mcp.<kebab-class-name>`. Override only when passing server-side data to the view.

**Blade view** — HTML structure + inline JS, everything in one file:
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
        <h1>Dashboard App</h1>
        <button id="run-btn">Run</button>
        <p id="output"></p>
    </div>
</x-mcp::app>
```

`createMcpApp` is a global pre-bundled by the package — no npm install, no imports, no Vite required. It handles connection, error handling, and host theming automatically. Read `references/app.md` for UiMeta/Csp/Permissions, `#[UiLinked]` tool linking, host theming CSS variables, and real-world patterns.

## Verification

1. Check `routes/ai.php` for proper registration
2. Test tool via MCP client
3. For UI apps with Vite entry: run `npm run build` and verify `public/build/` is populated

## Common Pitfalls

- Running `mcp:start` command (it hangs waiting for input)
- Using HTTPS locally with Node-based MCP clients
- Not using `search-docs` for the latest MCP documentation
- Not registering MCP server routes in `routes/ai.php`
- Do not register `ai.php` in `bootstrap.php`; it is registered automatically
- OAuth registration supports custom URI schemes (e.g., `cursor://`, `vscode://`) for native desktop clients via `mcp.custom_schemes` config
- For UI apps: using `@vite()` instead of inline script or `entry` prop (sandboxed iframes can't load external scripts)
- For UI apps with Vite: forgetting to run `npm run build` after changing JS/CSS (no hot reload in iframes)
