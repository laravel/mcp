<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Elicitation\Elicitation;
use Laravel\Mcp\Server\Elicitation\ElicitSchema;
use Laravel\Mcp\Server\Registrar;
use Laravel\Mcp\Server\Tool;
use Ramsey\Uuid\Uuid;

class HttpElicitationServer extends Server
{
    protected array $tools = [
        HttpFormElicitationTool::class,
    ];

    protected function generateSessionId(): string
    {
        return 'http-elicitation-session';
    }
}

class HttpFormElicitationTool extends Tool
{
    protected string $name = 'http-form-elicitation-tool';

    public function handle(Request $request, Elicitation $elicitation): Response
    {
        $result = $elicitation->form('What is your name?', fn (ElicitSchema $schema): array => [
            'name' => $schema->string('Your Name')->required(),
        ]);

        return Response::text('Hello, '.$result->get('name').'!');
    }
}

it('can complete an elicitation round trip over streamable http', function (): void {
    config()->set('cache.default', 'array');

    app(Registrar::class)->web('test-mcp-elicit', HttpElicitationServer::class);

    $elicitationRequestId = '00000000-0000-4000-8000-000000000001';

    Str::createUuidsUsingSequence([
        Uuid::fromString($elicitationRequestId),
    ]);

    try {
        $sessionId = initializeHttpElicitationConnection($this);

        cache()->put("mcp:elicitation:{$sessionId}:{$elicitationRequestId}", json_encode([
            'jsonrpc' => '2.0',
            'id' => $elicitationRequestId,
            'result' => [
                'action' => 'accept',
                'content' => ['name' => 'Taylor'],
            ],
        ], JSON_THROW_ON_ERROR), 120);

        $response = postJsonAcceptingEventStream($this, 'test-mcp-elicit', callHttpElicitationToolMessage(), $sessionId);

        $response->assertStatus(200);

        expect(strtolower((string) $response->headers->get('Content-Type')))->toBe('text/event-stream; charset=utf-8');

        $messages = parseJsonRpcMessagesFromSseStream($response->streamedContent());

        expect($messages)->toHaveCount(2)
            ->and($messages[0])->toMatchArray([
                'jsonrpc' => '2.0',
                'id' => $elicitationRequestId,
                'method' => 'elicitation/create',
            ])
            ->and($messages[0]['params']['mode'])->toBe('form')
            ->and($messages[0]['params']['message'])->toBe('What is your name?')
            ->and($messages[0]['params']['requestedSchema']['required'])->toBe(['name'])
            ->and($messages[1])->toEqual([
                'jsonrpc' => '2.0',
                'id' => 99,
                'result' => [
                    'content' => [[
                        'type' => 'text',
                        'text' => 'Hello, Taylor!',
                    ]],
                    'isError' => false,
                ],
            ]);
    } finally {
        Str::createUuidsUsing();
    }
});

it('uses the initialized protocol version when later http requests omit the protocol header', function (): void {
    config()->set('cache.default', 'array');

    app(Registrar::class)->web('test-mcp-elicit-without-version-header', HttpElicitationServer::class);

    $elicitationRequestId = '00000000-0000-4000-8000-000000000002';

    Str::createUuidsUsingSequence([
        Uuid::fromString($elicitationRequestId),
    ]);

    try {
        $sessionId = initializeHttpElicitationConnection(
            $this,
            'test-mcp-elicit-without-version-header',
            '2025-06-18',
            [Server::CAPABILITY_ELICITATION => []],
        );

        cache()->put("mcp:elicitation:{$sessionId}:{$elicitationRequestId}", json_encode([
            'jsonrpc' => '2.0',
            'id' => $elicitationRequestId,
            'result' => [
                'action' => 'accept',
                'content' => ['name' => 'Taylor'],
            ],
        ], JSON_THROW_ON_ERROR), 120);

        $response = postJsonAcceptingEventStream(
            $this,
            'test-mcp-elicit-without-version-header',
            callHttpElicitationToolMessage(),
            $sessionId,
            includeProtocolVersionHeader: false,
        );

        $response->assertStatus(200);

        $messages = parseJsonRpcMessagesFromSseStream($response->streamedContent());

        expect($messages[0]['method'])->toBe('elicitation/create')
            ->and($messages[0]['params'])->not->toHaveKey('mode')
            ->and($messages[1]['result']['content'][0]['text'])->toBe('Hello, Taylor!');
    } finally {
        Str::createUuidsUsing();
    }
});

it('stores initialized http session metadata longer than the elicitation response timeout', function (): void {
    config()->set('cache.default', 'array');
    config()->set('mcp.http_session_ttl', 600);

    app(Registrar::class)->web('test-mcp-elicit-session-ttl', HttpElicitationServer::class);

    $sessionId = initializeHttpElicitationConnection(
        $this,
        'test-mcp-elicit-session-ttl',
        '2025-06-18',
        [Server::CAPABILITY_ELICITATION => []],
    );

    $now = Carbon::now()->getPreciseTimestamp(3) / 1000;
    $items = cache()->getStore()->all();

    expect($items["mcp:session:{$sessionId}:clientCapabilities"]['expiresAt'] - $now)->toBeGreaterThan(120)
        ->and($items["mcp:session:{$sessionId}:protocolVersion"]['expiresAt'] - $now)->toBeGreaterThan(120);
});

it('routes http elicitation json rpc error responses to the waiting request', function (): void {
    config()->set('cache.default', 'array');

    app(Registrar::class)->web('test-mcp-elicit-error-response', HttpElicitationServer::class);

    $elicitationRequestId = '00000000-0000-4000-8000-000000000003';

    Str::createUuidsUsingSequence([
        Uuid::fromString($elicitationRequestId),
    ]);

    try {
        $sessionId = initializeHttpElicitationConnection($this, 'test-mcp-elicit-error-response');

        $response = $this->postJson('test-mcp-elicit-error-response', [
            'jsonrpc' => '2.0',
            'id' => $elicitationRequestId,
            'error' => [
                'code' => -32001,
                'message' => 'User rejected elicitation.',
            ],
        ], ['MCP-Session-Id' => $sessionId]);

        $response->assertStatus(202);
        expect($response->content())->toBe('');

        $response = postJsonAcceptingEventStream(
            $this,
            'test-mcp-elicit-error-response',
            callHttpElicitationToolMessage(),
            $sessionId,
        );

        $response->assertStatus(200);

        $messages = parseJsonRpcMessagesFromSseStream($response->streamedContent());

        expect($messages[0]['method'])->toBe('elicitation/create')
            ->and($messages[1]['error']['code'])->toBe(-32001)
            ->and($messages[1]['error']['message'])->toBe('User rejected elicitation.');
    } finally {
        Str::createUuidsUsing();
    }
});

it('returns a clear error when an http elicitation request is not streaming', function (): void {
    config()->set('cache.default', 'array');

    app(Registrar::class)->web('test-mcp-elicit-no-stream', HttpElicitationServer::class);

    $sessionId = initializeHttpElicitationConnection($this, 'test-mcp-elicit-no-stream');

    $response = $this->postJson(
        'test-mcp-elicit-no-stream',
        callHttpElicitationToolMessage(),
        ['MCP-Session-Id' => $sessionId, 'MCP-Protocol-Version' => '2025-11-25'],
    );

    $response->assertStatus(200);
    $response->assertJsonPath('error.message', 'HTTP elicitation requires a text/event-stream response. Send the request with Accept: text/event-stream.');
});

function initializeHttpElicitationConnection(
    $that,
    string $handle = 'test-mcp-elicit',
    string $protocolVersion = '2025-11-25',
    array $capabilities = [
        Server::CAPABILITY_ELICITATION => [
            'form' => true,
            'url' => true,
        ],
    ],
): string {
    $response = $that->postJson($handle, [
        'jsonrpc' => '2.0',
        'id' => 456,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => $protocolVersion,
            'capabilities' => $capabilities,
        ],
    ]);

    $response->assertStatus(200);

    $sessionId = $response->headers->get('MCP-Session-Id');

    expect($sessionId)->toBeString();

    $that->postJson($handle, initializeNotificationMessage(), ['MCP-Session-Id' => $sessionId]);

    return $sessionId;
}

function callHttpElicitationToolMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 99,
        'method' => 'tools/call',
        'params' => [
            'name' => 'http-form-elicitation-tool',
            'arguments' => [],
        ],
    ];
}

function postJsonAcceptingEventStream($that, string $uri, array $payload, string $sessionId, bool $includeProtocolVersionHeader = true)
{
    $content = json_encode($payload, JSON_THROW_ON_ERROR);

    $server = [
        'CONTENT_LENGTH' => strlen($content),
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'text/event-stream',
        'HTTP_MCP_SESSION_ID' => $sessionId,
    ];

    if ($includeProtocolVersionHeader) {
        $server['HTTP_MCP_PROTOCOL_VERSION'] = '2025-11-25';
    }

    return $that->call(
        'POST',
        $uri,
        [],
        [],
        [],
        $server,
        $content,
    );
}
