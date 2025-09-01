<?php

use Symfony\Component\Process\Process;

it('can initialize a connection over http', function () {
    $response = $this->postJson('test-mcp', initializeMessage());

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedInitializeResponse());
});

it('can list resources over http', function () {
    $sessionId = initializeHttpConnection($this);

    $response = $this->postJson(
        'test-mcp',
        listResourcesMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedListResourcesResponse());
});

it('can read a resource over http', function () {
    $sessionId = initializeHttpConnection($this);

    $response = $this->postJson(
        'test-mcp',
        readResourceMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedReadResourceResponse());
});

it('can list tools over http', function () {
    $sessionId = initializeHttpConnection($this);

    $response = $this->postJson(
        'test-mcp',
        listToolsMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedListToolsResponse());
});

it('can call a tool over http', function () {
    $sessionId = initializeHttpConnection($this);

    $response = $this->postJson(
        'test-mcp',
        callToolMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedCallToolResponse());
});

it('can handle a ping over http', function () {
    $sessionId = initializeHttpConnection($this);

    $response = $this->postJson(
        'test-mcp',
        pingMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedPingResponse());
});

it('can stream a tool response over http', function () {
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

it('can initialize a connection over stdio', function () {
    $process = new Process(['./vendor/bin/testbench', 'mcp:start', 'test-mcp']);
    $process->setInput(json_encode(initializeMessage()));

    $process->run();

    $output = json_decode($process->getOutput(), true);

    expect($output)->toEqual(expectedInitializeResponse());
});

it('can list tools over stdio', function () {
    $process = new Process(['./vendor/bin/testbench', 'mcp:start', 'test-mcp']);
    $process->setInput(json_encode(listToolsMessage()));

    $process->run();

    $output = json_decode($process->getOutput(), true);

    expect($output)->toEqual(expectedListToolsResponse());
});

it('can call a tool over stdio', function () {
    $process = new Process(['./vendor/bin/testbench', 'mcp:start', 'test-mcp']);
    $process->setInput(json_encode(callToolMessage()));

    $process->run();

    $output = json_decode($process->getOutput(), true);

    expect($output)->toEqual(expectedCallToolResponse());
});

it('can handle a ping over stdio', function () {
    $process = new Process(['./vendor/bin/testbench', 'mcp:start', 'test-mcp']);
    $process->setInput(json_encode(pingMessage()));

    $process->run();

    $output = json_decode($process->getOutput(), true);

    expect($output)->toEqual(expectedPingResponse());
});

it('can stream a tool response over stdio', function () {
    $process = new Process(['./vendor/bin/testbench', 'mcp:start', 'test-mcp']);
    $process->setInput(json_encode(callStreamingToolMessage()));

    $process->run();

    $output = $process->getOutput();
    $messages = parseJsonRpcMessagesFromStdout($output);

    expect($messages)->toEqual(expectedStreamingToolResponse());
});

it('can list dynamically added tools', function () {
    $sessionId = initializeHttpConnection($this, 'test-mcp-dynamic-tools');

    $response = $this->postJson(
        'test-mcp-dynamic-tools',
        listToolsMessage(),
        ['Mcp-Session-Id' => $sessionId],
    );

    $response->assertStatus(200);

    expect($response->json())->toEqual(expectedListToolsResponse());
});

function initializeHttpConnection($that, $handle = 'test-mcp')
{
    $response = $that->postJson($handle, initializeMessage());

    $sessionId = $response->headers->get('Mcp-Session-Id');

    $that->postJson($handle, initializeNotificationMessage(), ['Mcp-Session-Id' => $sessionId]);

    return $sessionId;
}
