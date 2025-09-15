<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

it('can initialize a connection over http', function (): void {
    $response = $this->postJson('test-mcp', initializeMessage());

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedInitializeResponse());
});

it('can list resources over http', function (): void {
    $sessionId = initializeHttpConnection($this);

    $response = $this->postJson(
        'test-mcp',
        listResourcesMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedListResourcesResponse());
});

it('can read a resource over http', function (): void {
    $sessionId = initializeHttpConnection($this);

    $response = $this->postJson(
        'test-mcp',
        readResourceMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedReadResourceResponse());
});

it('can list tools over http', function (): void {
    $sessionId = initializeHttpConnection($this);

    $response = $this->postJson(
        'test-mcp',
        listToolsMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedListToolsResponse());
});

it('can call a tool over http', function (): void {
    $sessionId = initializeHttpConnection($this);

    $response = $this->postJson(
        'test-mcp',
        callToolMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedCallToolResponse());
});

it('can handle a ping over http', function (): void {
    $sessionId = initializeHttpConnection($this);

    $response = $this->postJson(
        'test-mcp',
        pingMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedPingResponse());
});

it('can stream a tool response over http', function (): void {
    $sessionId = initializeHttpConnection($this);

    $response = $this->postJson(
        'test-mcp',
        callStreamingToolMessage(),
        ['Mcp-Session-Id' => $sessionId, 'Accept' => 'text/event-stream'],
    );

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

    $content = $response->streamedContent();
    $messages = parseJsonRpcMessagesFromSseStream($content);

    expect($messages)->toEqual(expectedStreamingToolResponse());
});

it('can initialize a connection over stdio', function (): void {
    $process = new Process([PHP_BINARY, 'vendor/bin/testbench', 'mcp:start', 'test-mcp']);
    $process->setInput(json_encode(initializeMessage()));

    $process->run(function (string $type, string $output): void {
        expect($type)->toEqual(Process::OUT);
        expect(json_decode($output, true))->toEqual(expectedInitializeResponse());
    });
    expect(true)->toBeTrue();
});

it('can list tools over stdio', function (): void {
    $process = new Process([PHP_BINARY, 'vendor/bin/testbench', 'mcp:start', 'test-mcp']);
    $process->setInput(json_encode(listToolsMessage()));

    $process->run();

    $output = json_decode($process->getOutput(), true);

    expect($output)->toEqual(expectedListToolsResponse());
});

it('can call a tool over stdio', function (): void {
    $process = new Process([PHP_BINARY, 'vendor/bin/testbench', 'mcp:start', 'test-mcp']);
    $process->setInput(json_encode(callToolMessage()));

    $process->run();

    $output = json_decode($process->getOutput(), true);

    expect($output)->toEqual(expectedCallToolResponse());
});

it('can handle a ping over stdio', function (): void {
    $process = new Process([PHP_BINARY, 'vendor/bin/testbench', 'mcp:start', 'test-mcp']);
    $process->setInput(json_encode(pingMessage()));

    $process->run();

    $output = json_decode($process->getOutput(), true);

    expect($output)->toEqual(expectedPingResponse());
});

it('can stream a tool response over stdio', function (): void {
    $process = new Process([PHP_BINARY, 'vendor/bin/testbench', 'mcp:start', 'test-mcp']);
    $process->setInput(json_encode(callStreamingToolMessage()));

    $process->run();

    $output = $process->getOutput();
    $messages = parseJsonRpcMessagesFromStdout($output);

    expect($messages)->toEqual(expectedStreamingToolResponse());
});

it('can list dynamically added tools', function (): void {
    $sessionId = initializeHttpConnection($this, 'test-mcp-dynamic-tools');

    $response = $this->postJson(
        'test-mcp-dynamic-tools',
        listToolsMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedListToolsResponse());
});

it('returns 405 for GET requests to MCP web routes', function (): void {
    $response = $this->get('test-mcp');

    $response->assertStatus(405);
    $response->assertSee('');
});

it('returns OAuth WWW-Authenticate header when OAuth routes are enabled and response is 401', function (): void {
    // Enable OAuth routes which registers the 'mcp.oauth.protected-resource' route
    app(\Laravel\Mcp\Server\Registrar::class)->oauthRoutes();

    // Create a test route that returns 401 to trigger the middleware
    Route::post('test-oauth-401', fn() => response()->json(['error' => 'unauthorized'], 401))->middleware([\Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader::class]);

    $response = $this->postJson('test-oauth-401', []);

    $response->assertStatus(401);
    $response->assertHeader('WWW-Authenticate');

    $wwwAuth = $response->headers->get('WWW-Authenticate');
    expect($wwwAuth)->toContain('Bearer realm="mcp"');
    expect($wwwAuth)->toContain('resource_metadata="'.url('/.well-known/oauth-protected-resource').'"');
});

it('returns Sanctum WWW-Authenticate header when OAuth routes are not enabled and response is 401', function (): void {
    // Create a test route that returns 401 to trigger the middleware
    Route::post('test-sanctum-401', fn() => response()->json(['error' => 'unauthorized'], 401))->middleware([\Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader::class]);

    $response = $this->postJson('test-sanctum-401', []);

    $response->assertStatus(401);
    $response->assertHeader('WWW-Authenticate');

    $wwwAuth = $response->headers->get('WWW-Authenticate');
    expect($wwwAuth)->toBe('Bearer realm="mcp", error="invalid_token"');
});

it('does not add WWW-Authenticate header when response is not 401', function (): void {
    app(\Laravel\Mcp\Server\Registrar::class)->oauthRoutes();

    $response = $this->postJson('test-mcp', initializeMessage());

    $response->assertStatus(200);
    $response->assertHeaderMissing('WWW-Authenticate');
});

function initializeHttpConnection($that, $handle = 'test-mcp')
{
    $response = $that->postJson($handle, initializeMessage());

    $sessionId = $response->headers->get('Mcp-Session-Id');

    $that->postJson($handle, initializeNotificationMessage(), ['Mcp-Session-Id' => $sessionId]);

    return $sessionId;
}
