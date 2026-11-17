<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tests\Fixtures\MirroredHeaderServer;
use Tests\Fixtures\MirroredHeaderTool;

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
            'code' => -32020,
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
        'code' => -32020,
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

    expect($response->json('error.code'))->toBe(-32020);
});

it('ignores whitespace surrounding a header value', function (string $header): void {
    $message = callToolMessage();
    $headers = mcpHeaders($message);

    $headers[$header] = " \t{$headers[$header]}  ";

    $response = $this->postJson('test-mcp', $message, $headers);

    $response->assertStatus(200);
})->with(['MCP-Protocol-Version', 'Mcp-Method', 'Mcp-Name']);

it('rejects a request without metadata that sends a stateless protocol version header', function (): void {
    $message = ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'server/discover', 'params' => []];

    $response = $this->postJson('test-mcp', $message, mcpHeaders($message));

    $response->assertStatus(400);

    expect($response->json())->toEqual([
        'jsonrpc' => '2.0',
        'id' => 3,
        'error' => [
            'code' => -32602,
            'message' => 'Invalid params: The request [_meta] is missing the required [io.modelcontextprotocol/protocolVersion] member.',
        ],
    ]);
});

it('answers the removed lifecycle methods with a 404 on a stateless request', function (string $method): void {
    $message = [
        'jsonrpc' => '2.0',
        'id' => 9,
        'method' => $method,
        'params' => ['_meta' => protocolMeta()],
    ];

    $response = $this->postJson('test-mcp', $message, mcpHeaders($message));

    $response->assertStatus(404);

    expect($response->json('error'))->toEqual([
        'code' => -32601,
        'message' => "The method [{$method}] was not found.",
    ]);
})->with(['initialize', 'ping']);

it('decodes a base64 sentinel name header before comparing it', function (): void {
    $message = callToolMessage();

    $response = $this->postJson('test-mcp', $message, [
        ...mcpHeaders($message),
        'Mcp-Name' => '=?base64?'.base64_encode((string) $message['params']['name']).'?=',
    ]);

    $response->assertStatus(200);
});

it('rejects a base64 name header that decodes to invalid UTF-8', function (): void {
    $message = callToolMessage();

    $response = $this->postJson('test-mcp', $message, [
        ...mcpHeaders($message),
        'Mcp-Name' => '=?base64?/w==?=',
    ]);

    $response->assertStatus(400);

    expect($response->json('error'))->toEqual([
        'code' => -32020,
        'message' => "Header mismatch: The [Mcp-Name] header value [=?base64?/w==?=] does not match the request body value [{$message['params']['name']}].",
    ]);
});

it('rejects a header carrying invalid UTF-8 bytes', function (string $header): void {
    $message = callToolMessage();

    $response = $this->postJson('test-mcp', $message, [
        ...mcpHeaders($message),
        $header => "\xFF",
    ]);

    $response->assertStatus(400);

    expect($response->json('error.code'))->toBe(-32020)
        ->and($response->json('error.message'))->toContain('header value [=?base64?/w==?=]');
})->with(['MCP-Protocol-Version', 'Mcp-Method', 'Mcp-Name']);

it('quotes a non-ASCII name header decoded in the mismatch message', function (): void {
    $message = callToolMessage();

    $response = $this->postJson('test-mcp', $message, [
        ...mcpHeaders($message),
        'Mcp-Name' => '=?base64?SGVsbG8sIOS4lueVjA==?=',
    ]);

    $response->assertStatus(400);

    expect($response->json('error.message'))->toBe("Header mismatch: The [Mcp-Name] header value [Hello, 世界] does not match the request body value [{$message['params']['name']}].");
});

it('encodes a decoded name header carrying control characters in the mismatch message', function (): void {
    $message = callToolMessage();

    $response = $this->postJson('test-mcp', $message, [
        ...mcpHeaders($message),
        'Mcp-Name' => '=?base64?'.base64_encode("a\nb").'?=',
    ]);

    $response->assertStatus(400);

    expect($response->json('error.message'))->toBe('Header mismatch: The [Mcp-Name] header value [=?base64?'.base64_encode("a\nb")."?=] does not match the request body value [{$message['params']['name']}].");
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
        'code' => -32020,
        'message' => 'Header mismatch: The [MCP-Protocol-Version] header is required.',
    ]);
});

it('skips header validation for a legacy request without protocol metadata', function (): void {
    $response = $this->postJson('test-mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list', 'params' => []]);

    $response->assertStatus(200);

    expect($response->json('result.tools'))->not->toBeEmpty();
});

it('serves tools after the legacy initialize handshake', function (string $version, bool $withHeader): void {
    $this->postJson('test-mcp', [...initializeMessage(), 'params' => [
        'protocolVersion' => $version,
        'capabilities' => (object) [],
        'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
    ]])->assertOk()->assertJsonPath('result.protocolVersion', $version);

    $headers = $withHeader ? ['MCP-Protocol-Version' => $version] : [];

    $this->postJson('test-mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $headers)
        ->assertStatus(202);

    $response = $this->postJson('test-mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list', 'params' => []], $headers);

    $response->assertStatus(200);

    expect(array_column($response->json('result.tools'), 'name'))->toContain('say-hi-tool');

    $message = callToolMessage();
    unset($message['params']['_meta']);

    $this->postJson('test-mcp', $message, $headers)
        ->assertOk()->assertJsonPath('result.content.0.text', 'Hello, John Doe!');
})->with(['2025-11-25', '2025-06-18', '2025-03-26'])->with([true, false]);

it('answers a legacy request carrying an unsupported protocol version header with a 400', function (): void {
    $response = $this->postJson('test-mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list', 'params' => []], [
        'MCP-Protocol-Version' => '1900-01-01',
    ]);

    $response->assertStatus(400);

    expect($response->json())->toEqual([
        'jsonrpc' => '2.0',
        'id' => 3,
        'error' => [
            'code' => -32022,
            'message' => 'Unsupported protocol version',
            'data' => [
                'supported' => ['2025-11-25', '2025-06-18', '2025-03-26'],
                'requested' => '1900-01-01',
            ],
        ],
    ]);
});

it('answers a legacy request carrying an invalid UTF-8 protocol version header with a 400', function (): void {
    $response = $this->postJson('test-mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list', 'params' => []], [
        'MCP-Protocol-Version' => "\xFF",
    ]);

    $response->assertStatus(400);

    expect($response->json())->toEqual([
        'jsonrpc' => '2.0',
        'id' => 3,
        'error' => [
            'code' => -32022,
            'message' => 'Unsupported protocol version',
            'data' => [
                'supported' => ['2025-11-25', '2025-06-18', '2025-03-26'],
                'requested' => '=?base64?/w==?=',
            ],
        ],
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
        'code' => -32020,
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

    expect($response->json('error'))->toEqual([
        'code' => -32020,
        'message' => 'Header mismatch: The [Mcp-Method] header value [=?base64?'.base64_encode('tools/call').'?=] does not match the request body value [tools/call].',
    ]);
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

function mirroredHeaderToolMessage(array $arguments): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'tools/call',
        'params' => [
            'name' => 'mirrored-header-tool',
            'arguments' => $arguments,
            '_meta' => protocolMeta(),
        ],
    ];
}

it('accepts mirrored parameter headers that match the body', function (array $arguments, array $headers): void {
    $message = mirroredHeaderToolMessage($arguments);

    $response = $this->postJson('test-mcp-mirrored-headers', $message, [...mcpHeaders($message), ...$headers]);

    $response->assertOk()->assertJsonPath('result.content.0.text', "Region: {$arguments['region']}");
})->with([
    'plain value' => [['region' => 'us-west1'], ['Mcp-Param-Region' => 'us-west1']],
    'base64 value' => [['region' => 'Hello, 世界'], ['Mcp-Param-Region' => '=?base64?SGVsbG8sIOS4lueVjA==?=']],
    'value without the base64 suffix is literal' => [['region' => '=?base64?SGVsbG8='], ['Mcp-Param-Region' => '=?base64?SGVsbG8=']],
    'lowercase header name and whitespace' => [['region' => 'us-west1'], ['mcp-param-region' => "  us-west1\t"]],
    'integer value' => [['region' => 'us-west1', 'replicas' => 3], ['Mcp-Param-Region' => 'us-west1', 'Mcp-Param-Replicas' => '3']],
]);

it('rejects mirrored parameter headers that are missing, malformed, or contradict the body', function (array $arguments, array $headers, string $error): void {
    $message = mirroredHeaderToolMessage($arguments);

    $response = $this->postJson('test-mcp-mirrored-headers', $message, [...mcpHeaders($message), ...$headers]);

    $response->assertStatus(400);

    expect($response->json())->toEqual([
        'jsonrpc' => '2.0',
        'id' => 7,
        'error' => ['code' => -32020, 'message' => "Header mismatch: {$error}"],
    ]);
})->with([
    'missing header' => [['region' => 'us-west1'], [], 'The [Mcp-Param-Region] header is required.'],
    'invalid base64 padding' => [['region' => 'Hello'], ['Mcp-Param-Region' => '=?base64?SGVsbG8?='], 'The [Mcp-Param-Region] header value does not match the request body value at [/region].'],
    'invalid base64 characters' => [['region' => 'Hello'], ['Mcp-Param-Region' => '=?base64?SGVs!!!bG8=?='], 'The [Mcp-Param-Region] header value does not match the request body value at [/region].'],
    'different value' => [['region' => 'us-west1'], ['Mcp-Param-Region' => 'eu-west1'], 'The [Mcp-Param-Region] header value does not match the request body value at [/region].'],
    'header without a body value' => [['region' => 'us-west1'], ['Mcp-Param-Region' => 'us-west1', 'Mcp-Param-Replicas' => '3'], 'The [Mcp-Param-Replicas] header is present but the request body has no [/replicas] argument.'],
    'body value of the wrong type' => [['region' => 'us-west1', 'replicas' => 'three'], ['Mcp-Param-Region' => 'us-west1', 'Mcp-Param-Replicas' => 'three'], 'The [Mcp-Param-Replicas] header value does not match the request body value at [/replicas].'],
]);

it('does not expect mirrored parameter headers outside of HTTP', function (): void {
    MirroredHeaderServer::tool(MirroredHeaderTool::class, ['region' => 'us-west1'])
        ->assertOk()
        ->assertSee('Region: us-west1');
});
