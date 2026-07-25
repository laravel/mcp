<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Laravel\Mcp\Server\Middleware\ValidateModernMcpRequest;
use Symfony\Component\HttpFoundation\Response;

function modernHttpRequest(array $body, array $headers = []): Request
{
    $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode($body));

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

function runMiddleware(Request $request): Response
{
    return (new ValidateModernMcpRequest)->handle(
        $request,
        fn (): Response => new Response('passed', 200),
    );
}

function modernBody(string $method, array $params = []): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => array_merge($params, [
            '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28'],
        ]),
    ];
}

it('passes legacy requests through untouched', function (): void {
    $response = runMiddleware(modernHttpRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]));

    expect($response->getContent())->toBe('passed');
});

it('passes valid modern requests through', function (): void {
    $response = runMiddleware(modernHttpRequest(modernBody('tools/list'), [
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => 'tools/list',
    ]));

    expect($response->getContent())->toBe('passed');
});

it('rejects a modern request whose protocol version header does not match the body', function (): void {
    $response = runMiddleware(modernHttpRequest(modernBody('tools/list'), [
        'MCP-Protocol-Version' => '2025-11-25',
        'Mcp-Method' => 'tools/list',
    ]));

    $payload = json_decode((string) $response->getContent(), true);

    expect($response->getStatusCode())->toBe(400)
        ->and($payload['error']['code'])->toBe(-32020)
        ->and($payload['error']['message'])->toContain('MCP-Protocol-Version');
});

it('rejects a modern request missing the protocol version header', function (): void {
    $response = runMiddleware(modernHttpRequest(modernBody('tools/list'), [
        'Mcp-Method' => 'tools/list',
    ]));

    expect($response->getStatusCode())->toBe(400);
});

it('rejects a modern request whose Mcp-Method header does not match the body', function (): void {
    $response = runMiddleware(modernHttpRequest(modernBody('tools/list'), [
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => 'prompts/list',
    ]));

    $payload = json_decode((string) $response->getContent(), true);

    expect($response->getStatusCode())->toBe(400)
        ->and($payload['error']['code'])->toBe(-32020)
        ->and($payload['error']['message'])->toContain('Mcp-Method');
});

it('requires the Mcp-Name header on named methods', function (string $method, string $parameter): void {
    $response = runMiddleware(modernHttpRequest(modernBody($method, [$parameter => 'target-name']), [
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => $method,
    ]));

    $payload = json_decode((string) $response->getContent(), true);

    expect($response->getStatusCode())->toBe(400)
        ->and($payload['error']['code'])->toBe(-32020)
        ->and($payload['error']['message'])->toContain('Mcp-Name');
})->with([
    'tools/call' => ['tools/call', 'name'],
    'prompts/get' => ['prompts/get', 'name'],
    'resources/read' => ['resources/read', 'uri'],
]);

it('accepts a matching Mcp-Name header', function (): void {
    $response = runMiddleware(modernHttpRequest(modernBody('tools/call', ['name' => 'calculator']), [
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => 'tools/call',
        'Mcp-Name' => 'calculator',
    ]));

    expect($response->getContent())->toBe('passed');
});

it('decodes the Base64 sentinel format when comparing Mcp-Name', function (): void {
    $name = 'héllo wörld';

    $response = runMiddleware(modernHttpRequest(modernBody('tools/call', ['name' => $name]), [
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => 'tools/call',
        'Mcp-Name' => '=?base64?'.base64_encode($name).'?=',
    ]));

    expect($response->getContent())->toBe('passed');
});

it('skips validation for notifications', function (): void {
    $response = runMiddleware(modernHttpRequest([
        'jsonrpc' => '2.0',
        'method' => 'notifications/cancelled',
        'params' => [
            '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28'],
        ],
    ]));

    expect($response->getContent())->toBe('passed');
});
