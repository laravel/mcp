<?php

declare(strict_types=1);

function executeSqlMessage(array $arguments): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'execute-sql-tool',
            'arguments' => $arguments,
            '_meta' => protocolMeta(),
        ],
    ];
}

it('advertises the declared annotations on the listed tool', function (): void {
    $message = listToolsMessage();

    $response = $this->postJson('test-mcp-mirrored', $message, mcpHeaders($message));

    expect($response->json('result.tools.0.inputSchema.properties'))
        ->toHaveKeys(['region', 'limit', 'query'])
        ->toHaveKey('region.x-mcp-header', 'Region')
        ->toHaveKey('limit.x-mcp-header', 'Limit')
        ->and($response->json('result.tools.0.inputSchema.properties.query'))
        ->not->toHaveKey('x-mcp-header');
});

it('accepts a call whose parameter headers mirror the arguments', function (): void {
    $message = executeSqlMessage(['region' => 'us-west1', 'limit' => 42, 'query' => 'select 1']);

    $response = $this->postJson('test-mcp-mirrored', $message, [
        ...mcpHeaders($message),
        'Mcp-Param-Region' => 'us-west1',
        'Mcp-Param-Limit' => '42',
    ]);

    $response->assertStatus(200);

    expect($response->json('result'))
        ->isError->toBeFalse()
        ->and($response->json('result.content.0.text'))->toBe('Executed in us-west1.');
});

it('rejects a call whose parameter header contradicts the arguments', function (): void {
    $message = executeSqlMessage(['region' => 'us-west1', 'query' => 'select 1']);

    $response = $this->postJson('test-mcp-mirrored', $message, [
        ...mcpHeaders($message),
        'Mcp-Param-Region' => 'us-east4',
    ]);

    $response->assertStatus(400);

    expect($response->json('error'))
        ->code->toBe(-32020)
        ->message->toContain('[Mcp-Param-Region] header value [us-east4] does not match')
        ->and($response->json())->not->toHaveKey('result');
});

it('rejects a call that omits a required parameter header', function (): void {
    $message = executeSqlMessage(['region' => 'us-west1', 'query' => 'select 1']);

    $response = $this->postJson('test-mcp-mirrored', $message, mcpHeaders($message));

    $response->assertStatus(400);

    expect($response->json('error'))
        ->code->toBe(-32020)
        ->message->toContain('[Mcp-Param-Region] header is required');
});

it('rejects a call that sends a parameter header for a missing argument', function (): void {
    $message = executeSqlMessage(['query' => 'select 1']);

    $response = $this->postJson('test-mcp-mirrored', $message, [
        ...mcpHeaders($message),
        'Mcp-Param-Region' => 'us-west1',
    ]);

    $response->assertStatus(400);

    expect($response->json('error'))
        ->code->toBe(-32020)
        ->message->toContain('was sent for the missing');
});

it('decodes a base64 sentinel parameter header before comparing it', function (): void {
    $message = executeSqlMessage(['region' => 'Hello, 世界', 'query' => 'select 1']);

    $response = $this->postJson('test-mcp-mirrored', $message, [
        ...mcpHeaders($message),
        'Mcp-Param-Region' => '=?base64?SGVsbG8sIOS4lueVjA==?=',
    ]);

    $response->assertStatus(200);

    expect($response->json('result.content.0.text'))->toBe('Executed in Hello, 世界.');
});

it('compares integer parameter headers numerically', function (): void {
    $message = executeSqlMessage(['region' => 'us-west1', 'limit' => 42, 'query' => 'select 1']);

    $response = $this->postJson('test-mcp-mirrored', $message, [
        ...mcpHeaders($message),
        'Mcp-Param-Region' => 'us-west1',
        'Mcp-Param-Limit' => '42.0',
    ]);

    $response->assertStatus(200);

    expect($response->json())->not->toHaveKey('error');
});

it('ignores parameter headers the tool does not declare', function (): void {
    $message = executeSqlMessage(['region' => 'us-west1', 'query' => 'select 1']);

    $response = $this->postJson('test-mcp-mirrored', $message, [
        ...mcpHeaders($message),
        'Mcp-Param-Region' => 'us-west1',
        'Mcp-Param-Unknown' => 'whatever',
    ]);

    $response->assertStatus(200);

    expect($response->json())->not->toHaveKey('error');
});
