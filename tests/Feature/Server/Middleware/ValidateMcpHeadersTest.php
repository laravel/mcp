<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

it('accepts a request whose headers mirror the body', function (): void {
    $response = $this->postJson('test-mcp', $message = callToolMessage(), mcpHeaders($message));

    $response->assertStatus(200);
});

it('rejects a request missing a required header', function (string $header): void {
    $message = callToolMessage();
    $headers = mcpHeaders($message);
    unset($headers[$header]);

    $response = $this->postJson('test-mcp', $message, $headers);

    $response->assertStatus(400);

    expect($response->json())->toEqual([
        'jsonrpc' => '2.0',
        'id' => $message['id'],
        'error' => [
            'code' => -32001,
            'message' => "Header mismatch: The [{$header}] header is required.",
        ],
    ]);
})->with(['MCP-Protocol-Version', 'Mcp-Method', 'Mcp-Name']);

it('rejects a request whose header contradicts the body', function (): void {
    $message = callToolMessage();

    $response = $this->postJson('test-mcp', $message, [
        ...mcpHeaders($message),
        'Mcp-Name' => 'some-other-tool',
    ]);

    $response->assertStatus(400);

    expect($response->json('error'))->toEqual([
        'code' => -32001,
        'message' => "Header mismatch: The [Mcp-Name] header value [some-other-tool] does not match the request body value [{$message['params']['name']}].",
    ]);
});

it('rejects a protocol version header that contradicts the body meta', function (): void {
    $message = listToolsMessage();

    $response = $this->postJson('test-mcp', $message, [
        ...mcpHeaders($message),
        'MCP-Protocol-Version' => '2025-11-25',
    ]);

    $response->assertStatus(400);

    expect($response->json('error.code'))->toBe(-32001);
});

it('decodes a base64 sentinel name header before comparing it', function (): void {
    $message = callToolMessage();

    $response = $this->postJson('test-mcp', $message, [
        ...mcpHeaders($message),
        'Mcp-Name' => '=?base64?'.base64_encode((string) $message['params']['name']).'?=',
    ]);

    $response->assertStatus(200);
});

it('does not require a name header for methods without a named target', function (): void {
    $message = listToolsMessage();

    $response = $this->postJson('test-mcp', $message, mcpHeaders($message));

    $response->assertStatus(200);

    expect(mcpHeaders($message))->not->toHaveKey('Mcp-Name');
});

it('answers an unsupported protocol version with a 400', function (): void {
    $message = listToolsMessage();
    $message['params']['_meta']['io.modelcontextprotocol/protocolVersion'] = '2025-11-25';

    $response = $this->postJson('test-mcp', $message, [
        ...mcpHeaders($message),
        'MCP-Protocol-Version' => '2025-11-25',
    ]);

    $response->assertStatus(400);

    expect($response->json('error'))->toEqual([
        'code' => -32022,
        'message' => 'Unsupported protocol version',
        'data' => [
            'supported' => ['2026-07-28'],
            'requested' => '2025-11-25',
        ],
    ]);
});

it('answers missing protocol metadata with a 400', function (): void {
    $message = listToolsMessage();
    unset($message['params']['_meta']['io.modelcontextprotocol/clientCapabilities']);

    $response = $this->postJson('test-mcp', $message, mcpHeaders($message));

    $response->assertStatus(400);

    expect($response->json('error.code'))->toBe(-32602);
});

it('answers an unknown method with a 404', function (): void {
    $message = [
        'jsonrpc' => '2.0',
        'id' => 9,
        'method' => 'unknown/method',
        'params' => ['_meta' => protocolMeta()],
    ];

    $response = $this->postJson('test-mcp', $message, mcpHeaders($message));

    $response->assertStatus(404);

    expect($response->json('error.code'))->toBe(-32601);
});

it('requires the protocol version header even when the body omits the meta member', function (): void {
    $message = listToolsMessage();
    unset($message['params']['_meta']['io.modelcontextprotocol/protocolVersion']);

    $headers = mcpHeaders($message);
    unset($headers['MCP-Protocol-Version']);

    $response = $this->postJson('test-mcp', $message, $headers);

    $response->assertStatus(400);

    expect($response->json('error'))->toEqual([
        'code' => -32001,
        'message' => 'Header mismatch: The [MCP-Protocol-Version] header is required.',
    ]);
});

it('requires the name header even when the body carries no usable name', function (): void {
    $message = callToolMessage();
    $message['params']['name'] = 123;

    $headers = mcpHeaders($message);
    unset($headers['Mcp-Name']);

    $response = $this->postJson('test-mcp', $message, $headers);

    $response->assertStatus(400);

    expect($response->json('error'))->toEqual([
        'code' => -32001,
        'message' => 'Header mismatch: The [Mcp-Name] header is required.',
    ]);
});

it('does not decode the base64 sentinel on headers other than the name', function (): void {
    $message = callToolMessage();

    $response = $this->postJson('test-mcp', $message, [
        ...mcpHeaders($message),
        'Mcp-Method' => '=?base64?'.base64_encode('tools/call').'?=',
    ]);

    $response->assertStatus(400);

    expect($response->json('error.code'))->toBe(-32001);
});

it('answers an internal error with a 500', function (): void {
    config(['app.debug' => false]);

    $this->app->bind(ListTools::class, fn (): ListTools => new class extends ListTools
    {
        public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
        {
            throw new RuntimeException('Something exploded.');
        }
    });

    $message = listToolsMessage();

    $response = $this->postJson('test-mcp', $message, mcpHeaders($message));

    $response->assertStatus(500);

    expect($response->json('error'))->toEqual([
        'code' => -32603,
        'message' => 'Something went wrong while processing the request.',
    ]);
});
