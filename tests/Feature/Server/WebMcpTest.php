<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Registrar;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\WebMcpServer;

class WebMcpIdentityServer extends Server
{
    protected array $tools = [
        WebMcpIdentityTool::class,
    ];
}

class WebMcpIdentityTool extends Tool
{
    public function handle(Request $request): string
    {
        return $request->user() instanceof Authenticatable
            ? 'Signed in'
            : 'Guest';
    }
}

class WebMcpPrimitiveServer extends Server
{
    protected array $resources = [
        WebMcpHiddenResource::class,
    ];

    protected array $prompts = [
        WebMcpHiddenPrompt::class,
    ];
}

class WebMcpHiddenResource extends Resource
{
    public function shouldRegister(): bool
    {
        return ! Mcp::viaWebMcp();
    }

    public function description(): string
    {
        return 'Only remote clients see me.';
    }

    public function handle(): string
    {
        return 'Nothing to see here.';
    }
}

class WebMcpHiddenPrompt extends Prompt
{
    protected string $description = 'Only remote clients see me.';

    public function shouldRegister(): bool
    {
        return ! Mcp::viaWebMcp();
    }

    public function handle(): Response
    {
        return Response::text('Nothing to see here.');
    }
}

function webMcpList(string $method): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => [
            '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => ProtocolVersion::LATEST->value,
                'io.modelcontextprotocol/clientCapabilities' => (object) [],
            ],
        ],
    ];
}

function webMcpCallTool(string $name): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $name,
            'arguments' => [],
            '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => ProtocolVersion::LATEST->value,
                'io.modelcontextprotocol/clientCapabilities' => (object) [],
            ],
        ],
    ];
}

function webMcpListTools(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [
            '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => ProtocolVersion::LATEST->value,
                'io.modelcontextprotocol/clientCapabilities' => (object) [],
            ],
        ],
    ];
}

it('does not register web mcp routes by default', function (): void {
    Mcp::web('default-mcp', WebMcpServer::class);

    $this->get('default-mcp/webmcp.js')->assertNotFound();
    $this->postJson('default-mcp/webmcp', webMcpListTools())->assertNotFound();
});

it('serves the bridge script when enabled', function (): void {
    config()->set('mcp.web_mcp.enabled', true);
    config()->set('mcp.web_mcp.middleware', []);

    Mcp::web('script-mcp', WebMcpServer::class);

    $response = $this->get('script-mcp/webmcp.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=utf-8');

    expect($response->getContent())
        ->toContain(url('script-mcp/webmcp'))
        ->toContain(ProtocolVersion::LATEST->value)
        ->toContain('document.modelContext');
});

it('exposes every tool through the bridge endpoint by default', function (): void {
    config()->set('mcp.web_mcp.enabled', true);
    config()->set('mcp.web_mcp.middleware', []);

    Mcp::web('default-bridge-mcp', ExampleServer::class);

    $tools = $this->postJson('default-bridge-mcp/webmcp', webMcpListTools())
        ->assertOk()
        ->json('result.tools');

    expect($tools)->toHaveCount(2);
});

it('hides primitives that opt out of web mcp registration', function (): void {
    config()->set('mcp.web_mcp.enabled', true);
    config()->set('mcp.web_mcp.middleware', []);

    Mcp::web('bridge-mcp', WebMcpServer::class);

    $tools = $this->postJson('bridge-mcp/webmcp', webMcpListTools())
        ->assertOk()
        ->json('result.tools');

    expect($tools)->toHaveCount(1)
        ->and($tools[0]['name'])->toBe('say-hi-tool');
});

it('still exposes every tool through the regular endpoint', function (): void {
    config()->set('mcp.web_mcp.enabled', true);
    config()->set('mcp.web_mcp.middleware', []);

    Mcp::web('full-mcp', WebMcpServer::class);

    $tools = $this->postJson('full-mcp', webMcpListTools(), [
        'MCP-Protocol-Version' => ProtocolVersion::LATEST->value,
        'MCP-Method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    expect($tools)->toHaveCount(2);
});

it('renders a script tag for a registered server', function (): void {
    config()->set('mcp.web_mcp.enabled', true);

    Mcp::web('tagged-mcp', WebMcpServer::class);

    expect((string) app(Registrar::class)->scripts('tagged-mcp'))
        ->toBe('<script src="'.url('tagged-mcp/webmcp.js').'" async></script>');
});

it('renders nothing when disabled', function (): void {
    Mcp::web('quiet-mcp', WebMcpServer::class);

    expect((string) app(Registrar::class)->scripts('quiet-mcp'))->toBe('');
});

it('resolves the authenticated user inside tools called through the bridge', function (): void {
    config()->set('mcp.web_mcp.enabled', true);
    config()->set('mcp.web_mcp.middleware', []);

    Mcp::web('identity-mcp', WebMcpIdentityServer::class);

    $this->actingAs(new class extends User {})
        ->postJson('identity-mcp/webmcp', webMcpCallTool('web-mcp-identity-tool'))
        ->assertOk()
        ->assertSee('Signed in');
});

it('treats an unauthenticated visitor as a guest', function (): void {
    config()->set('mcp.web_mcp.enabled', true);
    config()->set('mcp.web_mcp.middleware', []);

    Mcp::web('guest-mcp', WebMcpIdentityServer::class);

    $this->postJson('guest-mcp/webmcp', webMcpCallTool('web-mcp-identity-tool'))
        ->assertOk()
        ->assertSee('Guest');
});

it('hides resources and prompts that opt out of web mcp registration', function (): void {
    config()->set('mcp.web_mcp.enabled', true);
    config()->set('mcp.web_mcp.middleware', []);

    Mcp::web('primitive-mcp', WebMcpPrimitiveServer::class);

    expect($this->postJson('primitive-mcp/webmcp', webMcpList('resources/list'))->json('result.resources'))->toBe([])
        ->and($this->postJson('primitive-mcp/webmcp', webMcpList('prompts/list'))->json('result.prompts'))->toBe([]);
});

it('keeps those resources and prompts on the regular endpoint', function (): void {
    config()->set('mcp.web_mcp.enabled', true);

    Mcp::web('primitive-full-mcp', WebMcpPrimitiveServer::class);

    expect($this->postJson('primitive-full-mcp', webMcpList('resources/list'), [
        'MCP-Protocol-Version' => ProtocolVersion::LATEST->value,
        'MCP-Method' => 'resources/list',
    ])->json('result.resources'))->toHaveCount(1)
        ->and($this->postJson('primitive-full-mcp', webMcpList('prompts/list'), [
            'MCP-Protocol-Version' => ProtocolVersion::LATEST->value,
            'MCP-Method' => 'prompts/list',
        ])->json('result.prompts'))->toHaveCount(1);
});
