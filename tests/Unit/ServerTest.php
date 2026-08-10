<?php

use Laravel\Mcp\Enums\IconTheme;
use Laravel\Mcp\Schema\Icon;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Icon as IconAttribute;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\CustomMethodHandler;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\ThrowingMethodHandler;

it('rejects the initialize handshake', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(initializeMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 456,
        'error' => [
            'code' => -32601,
            'message' => 'The [initialize] handshake was removed in MCP 2026-07-28. Send the protocol version in the request [_meta] instead.',
            'data' => [
                'supported' => ['2026-07-28'],
            ],
        ],
    ]);
});

it('can handle a discover message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(discoverMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedDiscoverResponse());
});

it('rejects a request without the required protocol metadata', function (array $meta, string $message): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => ['_meta' => $meta],
    ]);

    ($transport->handler)($payload);

    expect(json_decode((string) $transport->sent[0], true))->toEqual([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => [
            'code' => -32602,
            'message' => $message,
        ],
    ]);
})->with([
    'missing version' => [
        ['io.modelcontextprotocol/clientCapabilities' => []],
        'Invalid params: The request [_meta] is missing the required [io.modelcontextprotocol/protocolVersion] member.',
    ],
    'missing capabilities' => [
        ['io.modelcontextprotocol/protocolVersion' => '2026-07-28'],
        'Invalid params: The request [_meta] is missing the required [io.modelcontextprotocol/clientCapabilities] member.',
    ],
    'null version' => [
        [
            'io.modelcontextprotocol/protocolVersion' => null,
            'io.modelcontextprotocol/clientCapabilities' => [],
        ],
        'Invalid params: The request [_meta] is missing the required [io.modelcontextprotocol/protocolVersion] member.',
    ],
    'non-string version' => [
        [
            'io.modelcontextprotocol/protocolVersion' => 123,
            'io.modelcontextprotocol/clientCapabilities' => [],
        ],
        'Invalid params: The request [_meta] is missing the required [io.modelcontextprotocol/protocolVersion] member.',
    ],
    'non-array capabilities' => [
        [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => 'nope',
        ],
        'Invalid params: The request [_meta] is missing the required [io.modelcontextprotocol/clientCapabilities] member.',
    ],
    'list capabilities' => [
        [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => ['elicitation'],
        ],
        'Invalid params: The request [_meta] is missing the required [io.modelcontextprotocol/clientCapabilities] member.',
    ],
]);

it('rejects an unsupported protocol version', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [
            '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2025-11-25',
                'io.modelcontextprotocol/clientCapabilities' => [],
            ],
        ],
    ]);

    ($transport->handler)($payload);

    expect(json_decode((string) $transport->sent[0], true))->toEqual([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => [
            'code' => -32022,
            'message' => 'Unsupported protocol version',
            'data' => [
                'supported' => ['2026-07-28'],
                'requested' => '2025-11-25',
            ],
        ],
    ]);
});

it('can add a capability', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->addCapability('customFeature.enabled', true);
    $server->addCapability('anotherFeature');

    $server->start();

    $payload = json_encode(discoverMessage());

    ($transport->handler)($payload);

    $jsonResponse = $transport->sent[0];

    $capabilities = (fn (): array => $this->capabilities)->call($server);

    $expectedCapabilitiesJson = json_encode(array_merge($capabilities, [
        'customFeature' => [
            'enabled' => true,
        ],
        'anotherFeature' => (object) [],
    ]));

    $this->assertStringContainsString($expectedCapabilitiesJson, $jsonResponse);
});

it('can handle a list tools message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(listToolsMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedListToolsResponse());
});

it('can handle a call tool message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(callToolMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedCallToolResponse());
});

it('can handle a notification message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
    ]);

    ($transport->handler)($payload);

    expect($transport->sent)->toHaveCount(0);
});

it('can handle an unknown method', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 789,
        'method' => 'unknown/method',
        'params' => ['_meta' => protocolMeta()],
    ]);

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 789,
        'error' => [
            'code' => -32601,
            'message' => 'The method [unknown/method] was not found.',
        ],
    ]);
});

it('returns protocol errors for invalid parameter shapes', function (mixed $params, string $message): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 272,
        'method' => 'tools/call',
        'params' => $params,
    ]);

    ($transport->handler)($payload);

    expect(json_decode((string) $transport->sent[0], true))->toEqual([
        'jsonrpc' => '2.0',
        'id' => 272,
        'error' => [
            'code' => -32602,
            'message' => $message,
        ],
    ]);
})->with([
    'top-level params' => ['invalid', 'Invalid params: The [params] member must be an object.'],
    'tool arguments' => [[
        'name' => 'say-hi-tool',
        'arguments' => 'invalid',
        '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => [],
        ],
    ], 'Invalid params: The [arguments] member must be an object.'],
]);

it('handles json decode errors', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $invalidJsonPayload = '{"jsonrpc": "2.0", "id": 123, "method": "initialize", "params": {}';

    // Malformed JSON
    ($transport->handler)($invalidJsonPayload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toBe([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32700,
            'message' => 'Parse error: Invalid JSON was received by the server.',
        ],
    ]);
});

it('can handle a custom method message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->addMethod('custom/method', CustomMethodHandler::class);

    $this->app->bind(CustomMethodHandler::class, fn (): CustomMethodHandler => new CustomMethodHandler('custom-dependency'));

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 12345,
        'method' => 'custom/method',
        'params' => ['_meta' => protocolMeta()],
    ]);

    ($transport->handler)($payload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 12345,
        'result' => [
            'resultType' => 'complete',
            'message' => 'Custom method executed successfully!',
            '_meta' => [
                'io.modelcontextprotocol/serverInfo' => [
                    'name' => 'Laravel MCP Server',
                    'version' => '0.0.1',
                ],
            ],
        ],
    ]);
});

it('keeps the result type and metadata a method supplied itself', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->addMethod('custom/method', OpinionatedMethodHandler::class);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'custom/method',
        'params' => ['_meta' => protocolMeta()],
    ]);

    ($transport->handler)($payload);

    expect(json_decode((string) $transport->sent[0], true)['result'])->toEqual([
        'resultType' => 'input_required',
        '_meta' => [
            'io.modelcontextprotocol/serverInfo' => [
                'name' => 'Laravel MCP Server',
                'version' => '0.0.1',
            ],
            'app/trace' => 'abc',
        ],
    ]);
});

it('no longer answers a ping message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 789,
        'method' => 'ping',
        'params' => ['_meta' => protocolMeta()],
    ]);

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response['error']['code'])->toBe(-32601);
});

it('lets a dual-era server serve initialize through addMethod', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->addMethod('initialize', CustomMethodHandler::class);

    $this->app->bind(CustomMethodHandler::class, fn (): CustomMethodHandler => new CustomMethodHandler('custom-dependency'));

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 456,
        'method' => 'initialize',
        'params' => ['_meta' => protocolMeta()],
    ]);

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response['result']['message'])->toBe('Custom method executed successfully!');
});

it('discovers an empty capability set as a json object', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    (function (): void {
        $this->capabilities = [];
    })->call($server);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 456,
        'method' => 'server/discover',
        'params' => ['_meta' => protocolMeta()],
    ]);

    ($transport->handler)($payload);

    expect((string) $transport->sent[0])->toContain('"capabilities":{}');
});

it('calls boot method on connect', function (): void {
    $transport = new ArrayTransport;

    $server = new class($transport) extends Server
    {
        public function boot(): void
        {
            $this->bootCalled = true;
        }
    };
    $server->start();

    expect($server->bootCalled)->toBeTrue('The boot() method was not called on connect.');
});

it('can handle a tool streaming multiple messages', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(callStreamingToolMessage());

    ($transport->handler)($payload);

    $messages = array_map(fn ($msg): mixed => json_decode((string) $msg, true), $transport->sent);

    expect($messages)->toEqual(expectedStreamingToolResponse());
});

it('handles capability with non-array existing value', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    // First set a non-array value
    $server->addCapability('feature');

    // Then try to add a nested capability to it
    $server->addCapability('feature.enabled', true);

    $server->start();

    $payload = json_encode(discoverMessage());

    ($transport->handler)($payload);

    $capabilities = (fn (): array => $this->capabilities)->call($server);

    expect($capabilities['feature'])->toBeArray();
    expect($capabilities['feature']['enabled'])->toBeTrue();
});

it('handles exceptions in debug mode', function (): void {
    config()->set('app.debug', true);

    $transport = new ArrayTransport;
    $server = new class($transport) extends Server
    {
        protected array $methods = [
            'test/method' => ThrowingMethodHandler::class,
        ];
    };

    $this->app->bind(ThrowingMethodHandler::class, fn (): Method => new class implements Method
    {
        public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
        {
            throw new Exception('Test exception');
        }
    });

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 999,
        'method' => 'test/method',
        'params' => ['_meta' => protocolMeta()],
    ]);

    expect(function () use ($transport, $payload): void {
        ($transport->handler)($payload);
    })->toThrow(Exception::class, 'Test exception');
});

it('handles exceptions in production mode', function (): void {
    config()->set('app.debug', false);

    $transport = new ArrayTransport;
    $server = new class($transport) extends Server
    {
        protected array $methods = [
            'test/method' => ThrowingMethodHandler::class,
        ];
    };

    $this->app->bind(ThrowingMethodHandler::class, fn (): Method => new class implements Method
    {
        public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
        {
            throw new Exception('Test exception');
        }
    });

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 999,
        'method' => 'test/method',
        'params' => ['_meta' => protocolMeta()],
    ]);

    ($transport->handler)($payload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 999,
        'error' => [
            'code' => -32603,
            'message' => 'Something went wrong while processing the request.',
        ],
    ]);
});

it('forwards icons() into the server context', function (): void {
    $server = new class(new ArrayTransport) extends Server
    {
        protected function icons(): array
        {
            return [new Icon('https://example.com/server.png', mimeType: 'image/png')];
        }
    };

    $context = $server->createContext();

    expect($context->implementation->icons)->toHaveCount(1)
        ->and($context->implementation->icons[0])->toBeInstanceOf(Icon::class)
        ->and($context->implementation->icons[0]->src)->toBe('https://example.com/server.png');
});

it('reads class-level Icon attributes into the server context', function (): void {
    $server = new IconAttributeServer(new ArrayTransport);

    $context = $server->createContext();

    expect($context->implementation->icons)->toHaveCount(2)
        ->and($context->implementation->icons[0]->src)->toBe('https://example.com/icon.png')
        ->and($context->implementation->icons[0]->mimeType)->toBe('image/png')
        ->and($context->implementation->icons[0]->sizes)->toBe(['48x48'])
        ->and($context->implementation->icons[1]->src)->toBe('https://example.com/icon-dark.svg')
        ->and($context->implementation->icons[1]->theme)->toBe(IconTheme::Dark);
});

it('merges Icon attributes with icons() method output', function (): void {
    $server = new MixedIconServer(new ArrayTransport);

    $context = $server->createContext();

    expect($context->implementation->icons)->toHaveCount(2)
        ->and($context->implementation->icons[0]->src)->toBe('https://example.com/from-attribute.png')
        ->and($context->implementation->icons[1]->src)->toBe('https://example.com/from-method.png');
});

#[IconAttribute('https://example.com/icon.png', mimeType: 'image/png', sizes: ['48x48'])]
#[IconAttribute('https://example.com/icon-dark.svg', theme: IconTheme::Dark)]
class IconAttributeServer extends Server {}

#[IconAttribute('https://example.com/from-attribute.png')]
class MixedIconServer extends Server
{
    protected function icons(): array
    {
        return [new Icon('https://example.com/from-method.png')];
    }
}

class OpinionatedMethodHandler implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        return JsonRpcResponse::result($request->id, [
            'resultType' => 'input_required',
            '_meta' => ['app/trace' => 'abc'],
        ]);
    }
}
